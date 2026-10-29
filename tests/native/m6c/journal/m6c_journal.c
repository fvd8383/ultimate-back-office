/* Test-only, Linux PHP 8.4 NTS CLI. No libc interposition and no global preload.
 * Intercept the actual PHP internal handler BEFORE its operation, returning false
 * only when the fixed shared policy requests it. This is an injected API failure,
 * NOT evidence of a failing syscall. The finally/fclose handler still runs normally.
 */
#ifdef HAVE_CONFIG_H
# include "config.h"
#endif
#include "php.h"
#include "main/php_streams.h"
#include "SAPI.h"
#include <sys/stat.h>
#include <unistd.h>
#include <errno.h>
#include <limits.h>
#if defined(PHP_WIN32) || defined(ZTS) || PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500
# error "M6C journal requires Linux PHP 8.4 NTS; unsupported is never PASS"
#endif

#define WORK "/var/lib/ubo-m6c/work"
#define WORKER "/opt/ubo/tests/support/WebsitePlatformM6CJournalWorker.php"
#define POLICY "/opt/ubo/tests/support/WebsitePlatformM6CJournalFault.php"
static const char *names[]={"fopen","fwrite","fflush","fsync","fclose","chmod","rename"};
static zif_handler original[7];
static zend_function *functions[7];
static struct {
    int armed, closed, bound, matched, injected, off_target, callback;
    zend_long producer; zend_ulong resource; dev_t dev; ino_t ino;
    char run[33], job[37], parent[PATH_MAX], pending[PATH_MAX], final[PATH_MAX];
} state;

static void deny(void) { zend_throw_error(NULL,"journal_native_identity_or_phase"); }
static int hex(const char *s,size_t n) {
    for(size_t i=0;i<n;i++)if(!((s[i]>='a'&&s[i]<='f')||(s[i]>='0'&&s[i]<='9')))return 0;
    return 1;
}
static int uuid(const char *s,size_t n) {
    if(n!=36)return 0;
    for(size_t i=0;i<n;i++) { if(i==8||i==13||i==18||i==23){if(s[i]!='-')return 0;}else if(!hex(s+i,1))return 0; }
    return 1;
}
static int directory(const char *p,uid_t owner,mode_t mode) {
    struct stat st;char real[PATH_MAX];
    return lstat(p,&st)==0&&S_ISDIR(st.st_mode)&&st.st_uid==owner&&(st.st_mode&07777)==mode
        &&realpath(p,real)!=NULL&&strcmp(p,real)==0;
}
static int roots(void) {
    const char *parents[]={"/","/var","/var/lib","/var/lib/ubo-m6c"};struct stat st;char real[PATH_MAX];
    for(int i=0;i<4;i++)if(lstat(parents[i],&st)||!S_ISDIR(st.st_mode)||st.st_uid!=0||(st.st_mode&0022)
        ||!realpath(parents[i],real)||strcmp(parents[i],real))return 0;
    return directory(WORK,2000,0700);
}
static int file_identity(php_stream *stream) {
    struct stat st;php_stream_statbuf ss;
    if(!roots()||!directory(state.parent,2000,0700)||lstat(state.pending,&st)||!S_ISREG(st.st_mode)
        ||st.st_uid!=2000||st.st_nlink!=1||(st.st_mode&0077))return 0;
    if(stream&&(php_stream_stat(stream,&ss)||ss.sb.st_dev!=st.st_dev||ss.sb.st_ino!=st.st_ino
        ||ss.sb.st_nlink!=1||!S_ISREG(ss.sb.st_mode)))return 0;
    if(state.bound)return st.st_dev==state.dev&&st.st_ino==state.ino;
    state.dev=st.st_dev;state.ino=st.st_ino;state.bound=1;return 1;
}
static int caller(zend_execute_data *ex,int operation) {
    zend_execute_data *p=ex->prev_execute_data;
    if(!p||!p->func||p->func->type!=ZEND_USER_FUNCTION||!p->func->common.scope)return 0;
    const char *class_name=ZSTR_VAL(p->func->common.scope->name),*method=ZSTR_VAL(p->func->common.function_name);
    const char *file=ZSTR_VAL(p->func->op_array.filename);
    return operation==6
        ? !strcmp(class_name,"LocalSiteArtifactStore")&&!strcmp(method,"createCandidate")&&!strcmp(file,"/opt/ubo/private/classes/LocalSiteArtifactStore.php")
        : !strcmp(class_name,"SiteArtifactFiles")&&!strcmp(method,"writeNew")&&!strcmp(file,"/opt/ubo/private/classes/SiteArtifactFiles.php");
}
static int observe(int op,const char *phase,int ok,zend_long bytes) {
    zval fn,ret,args[4];int result=-1;
    if(state.callback){deny();return -1;}
    ZVAL_STRING(&fn,"m6c_journal_observe");ZVAL_STRING(&args[0],names[op]);ZVAL_STRING(&args[1],phase);
    ZVAL_BOOL(&args[2],ok);ZVAL_LONG(&args[3],bytes);ZVAL_UNDEF(&ret);state.callback=1;
    if(call_user_function(EG(function_table),NULL,&fn,&ret,4,args)==SUCCESS&&!EG(exception)
        &&(Z_TYPE(ret)==IS_TRUE||Z_TYPE(ret)==IS_FALSE))result=Z_TYPE(ret)==IS_TRUE;
    state.callback=0;zval_ptr_dtor(&fn);zval_ptr_dtor(&args[0]);zval_ptr_dtor(&args[1]);zval_ptr_dtor(&ret);
    if(result<0&&!EG(exception))deny();return result;
}
static int equal_string(zval *v,const char *s) {
    return Z_TYPE_P(v)==IS_STRING&&Z_STRLEN_P(v)==strlen(s)&&memcmp(Z_STRVAL_P(v),s,strlen(s))==0;
}
static ZEND_NAMED_FUNCTION(dispatch) {
    int op=-1;for(int i=0;i<7;i++)if(execute_data->func==functions[i]){op=i;break;}
    if(op<0){deny();return;}
    if(!state.armed||state.callback||ZEND_CALL_NUM_ARGS(execute_data)<1){original[op](INTERNAL_FUNCTION_PARAM_PASSTHRU);return;}
    zval *arg=ZEND_CALL_ARG(execute_data,1);php_stream *stream=NULL;int match=0;
    if(op==0||op==5||op==6)match=equal_string(arg,state.pending);
    else if(Z_TYPE_P(arg)==IS_RESOURCE&&(Z_RES_P(arg)->type==php_file_le_stream()||Z_RES_P(arg)->type==php_file_le_pstream())) {
        stream=(php_stream*)Z_RES_P(arg)->ptr;
        match=stream&&stream->orig_path&&!strcmp(stream->orig_path,state.pending);
    }
    if(!match){if(state.off_target<4096)state.off_target++;original[op](INTERNAL_FUNCTION_PARAM_PASSTHRU);return;}
    if(!caller(execute_data,op)||!roots()||!directory(state.parent,2000,0700)){deny();return;}
    if(op==0){
        struct stat st;
        if(state.bound||ZEND_CALL_NUM_ARGS(execute_data)!=2||!equal_string(ZEND_CALL_ARG(execute_data,2),"xb")
            ||lstat(state.pending,&st)==0||errno!=ENOENT){deny();return;}
    }else if(!file_identity(stream)||(stream&&(state.closed||Z_RES_HANDLE_P(arg)!=state.resource))){deny();return;}
    if(op==5&&(ZEND_CALL_NUM_ARGS(execute_data)!=2||Z_TYPE_P(ZEND_CALL_ARG(execute_data,2))!=IS_LONG
        ||Z_LVAL_P(ZEND_CALL_ARG(execute_data,2))!=0600)){deny();return;}
    if(op==6){struct stat st;
        if(!state.closed||ZEND_CALL_NUM_ARGS(execute_data)!=2||!equal_string(ZEND_CALL_ARG(execute_data,2),state.final)
            ||lstat(state.final,&st)==0||errno!=ENOENT){deny();return;}}
    state.matched++;int inject=observe(op,"before",0,0);if(inject<0)return;
    if(inject){
        if((op!=2&&op!=3&&op!=6)||++state.injected!=1){deny();return;}
        ZVAL_FALSE(return_value);observe(op,"after",0,0);return; // Original operation is NOT performed.
    }
    zend_long length=0;
    if(op==1&&ZEND_CALL_NUM_ARGS(execute_data)==2&&Z_TYPE_P(ZEND_CALL_ARG(execute_data,2))==IS_STRING)
        length=(zend_long)Z_STRLEN_P(ZEND_CALL_ARG(execute_data,2));
    original[op](INTERNAL_FUNCTION_PARAM_PASSTHRU);if(EG(exception))return;
    int ok=Z_TYPE_P(return_value)==IS_TRUE;
    if(op==0){
        ok=Z_TYPE_P(return_value)==IS_RESOURCE;
        if(ok){stream=(php_stream*)zend_fetch_resource2_ex(return_value,"stream",php_file_le_stream(),php_file_le_pstream());
            ok=stream&&stream->orig_path&&!strcmp(stream->orig_path,state.pending)&&file_identity(stream);
            if(ok)state.resource=Z_RES_HANDLE_P(return_value);}
    }
    if(op==1)ok=length>0&&length<=4096&&Z_TYPE_P(return_value)==IS_LONG&&Z_LVAL_P(return_value)==length;
    if(op==4&&ok)state.closed=1;
    if(op==5&&ok){struct stat st;ok=lstat(state.pending,&st)==0&&(st.st_mode&07777)==0600&&file_identity(NULL);}
    if(op==6&&ok){struct stat st;ok=lstat(state.final,&st)==0&&S_ISREG(st.st_mode)&&st.st_nlink==1
        &&st.st_dev==state.dev&&st.st_ino==state.ino&&lstat(state.pending,&st)<0&&errno==ENOENT;}
    observe(op,"after",ok,op==1&&ok?length:0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_arm,0,3,_IS_BOOL,0)
    ZEND_ARG_TYPE_INFO(0,run,IS_STRING,0)
    ZEND_ARG_TYPE_INFO(0,job,IS_STRING,0)
    ZEND_ARG_TYPE_INFO(0,producer,IS_LONG,0)
ZEND_END_ARG_INFO()
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_status,0,0,IS_ARRAY,0)
ZEND_END_ARG_INFO()
PHP_FUNCTION(m6c_journal_arm) {
    char *run,*job;size_t run_len,job_len;zend_long producer;char host[128];
    ZEND_PARSE_PARAMETERS_START(3,3) Z_PARAM_STRING(run,run_len) Z_PARAM_STRING(job,job_len) Z_PARAM_LONG(producer) ZEND_PARSE_PARAMETERS_END();
    zend_execute_data *p=execute_data->prev_execute_data;
    zend_function *policy=zend_hash_str_find_ptr(EG(function_table),"m6c_journal_observe",sizeof("m6c_journal_observe")-1);
    zend_function *authorize=zend_hash_str_find_ptr(EG(function_table),"m6c_journal_authorize",sizeof("m6c_journal_authorize")-1);
    if(state.armed||strcmp(sapi_module.name,"cli")||geteuid()!=2000||getegid()!=2000||run_len!=32||!hex(run,32)
        ||!uuid(job,job_len)||producer<1||!p||p->func->type!=ZEND_USER_FUNCTION||strcmp(ZSTR_VAL(p->func->op_array.filename),WORKER)
        ||!policy||policy->type!=ZEND_USER_FUNCTION||strcmp(ZSTR_VAL(policy->op_array.filename),POLICY)
        ||!authorize||authorize->type!=ZEND_USER_FUNCTION||strcmp(ZSTR_VAL(authorize->op_array.filename),POLICY)||!roots()) {deny();RETURN_THROWS();}
    char expected[64];snprintf(expected,sizeof(expected),"m6c-runtime-%s",run);
    if(gethostname(host,sizeof(host))||!memchr(host,0,sizeof(host))||strcmp(host,expected)){deny();RETURN_THROWS();}
    zval fn,ret,args[3];ZVAL_STRING(&fn,"m6c_journal_authorize");ZVAL_STRINGL(&args[0],run,run_len);
    ZVAL_STRINGL(&args[1],job,job_len);ZVAL_LONG(&args[2],producer);ZVAL_UNDEF(&ret);
    int authorized=call_user_function(EG(function_table),NULL,&fn,&ret,3,args)==SUCCESS&&!EG(exception)&&Z_TYPE(ret)==IS_TRUE;
    zval_ptr_dtor(&fn);zval_ptr_dtor(&ret);zval_ptr_dtor(&args[0]);zval_ptr_dtor(&args[1]);
    if(!authorized){if(!EG(exception))deny();RETURN_THROWS();}
    memcpy(state.run,run,33);memcpy(state.job,job,37);state.producer=producer;
    snprintf(state.parent,sizeof(state.parent),WORK "/%s",job);
    snprintf(state.pending,sizeof(state.pending),WORK "/%s/attempt-%ld.json.pending",job,(long)producer);
    snprintf(state.final,sizeof(state.final),WORK "/%s/attempt-%ld.json",job,(long)producer);
    struct stat st;
    if(lstat(state.parent,&st)==0&&!directory(state.parent,2000,0700)){deny();RETURN_THROWS();}
    if(lstat(state.pending,&st)==0||errno!=ENOENT||lstat(state.final,&st)==0||errno!=ENOENT){deny();RETURN_THROWS();}
    state.armed=1;RETURN_TRUE;
}
PHP_FUNCTION(m6c_journal_status) {
    ZEND_PARSE_PARAMETERS_NONE();if(!state.armed){deny();RETURN_THROWS();}
    array_init(return_value);add_assoc_string(return_value,"run",state.run);add_assoc_string(return_value,"job",state.job);
    add_assoc_long(return_value,"producer",state.producer);add_assoc_long(return_value,"matched",state.matched);
    add_assoc_long(return_value,"injected",state.injected);add_assoc_long(return_value,"off_target",state.off_target);
}
static const zend_function_entry entries[]={PHP_FE(m6c_journal_arm,arginfo_arm) PHP_FE(m6c_journal_status,arginfo_status) PHP_FE_END};
PHP_MINIT_FUNCTION(m6c_journal) {
    if(strcmp(sapi_module.name,"cli"))return FAILURE;
    for(int i=0;i<7;i++){
        functions[i]=zend_hash_str_find_ptr(CG(function_table),names[i],strlen(names[i]));
        if(!functions[i]||functions[i]->type!=ZEND_INTERNAL_FUNCTION||functions[i]->internal_function.frameless_function_infos)return FAILURE;
    }
    for(int i=0;i<7;i++){original[i]=functions[i]->internal_function.handler;functions[i]->internal_function.handler=dispatch;}
    return SUCCESS;
}
PHP_MSHUTDOWN_FUNCTION(m6c_journal) {
    for(int i=0;i<7;i++)if(original[i]&&functions[i]->internal_function.handler==dispatch)functions[i]->internal_function.handler=original[i];
    return SUCCESS;
}
PHP_RINIT_FUNCTION(m6c_journal) { memset(&state,0,sizeof(state));return SUCCESS; }
zend_module_entry m6c_journal_module_entry={STANDARD_MODULE_HEADER,"m6c_journal",entries,
    PHP_MINIT(m6c_journal),PHP_MSHUTDOWN(m6c_journal),PHP_RINIT(m6c_journal),NULL,NULL,"1.0.0",STANDARD_MODULE_PROPERTIES};
#ifdef COMPILE_DL_M6C_JOURNAL
ZEND_GET_MODULE(m6c_journal)
#endif
