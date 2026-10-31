PHP_ARG_ENABLE([m6c_journal], [whether to build the test-only M6C journal interceptor],
  [AS_HELP_STRING([--enable-m6c-journal], [Build M6C journal test extension])], [no])
if test "$PHP_M6C_JOURNAL" != "no"; then
  PHP_NEW_EXTENSION([m6c_journal], [m6c_journal.c], [$ext_shared])
fi
