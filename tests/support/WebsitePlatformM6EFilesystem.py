"""Private Windows files; explicit models for dirfd, Unix metadata/timestamps, renameat2 and directory fsync.

Tree.write_new/read/publish_immutable and all their sequencing remain production code.
This adapter is never operational admission and supplies no Linux acceptance evidence.
"""
import os
from pathlib import Path
import stat
from types import SimpleNamespace


class FilesystemModel:
    O_DIRECTORY = 1 << 25
    O_NOFOLLOW = 1 << 26
    O_CLOEXEC = 0
    O_NONBLOCK = 0

    def __init__(self, root):
        self.root = Path(root)
        self.dirs = {}
        self.files = {}
        self.flags = {}
        self.meta = {}
        self.next_fd = 100000
        self.fault = None
        self.events = []
        self.before_publish = None
        self.cleanup_fault = False

    def __getattr__(self, name):
        return getattr(os, name)

    def fail(self, fault):
        self.events.append(('injected', fault))
        raise OSError('injected_' + ('partial_write' if fault == 'partial' else fault))

    def directory(self, path):
        path = Path(path)
        if not path.is_dir() or path.is_symlink():
            raise OSError('model_directory')
        self.next_fd += 1
        self.dirs[self.next_fd] = path
        return self.next_fd

    def path(self, name, dir_fd=None):
        return self.dirs[dir_fd] / name if dir_fd is not None else Path(name)

    def open(self, name, flags, mode=0o777, *, dir_fd=None):
        path = self.path(name, dir_fd)
        if path.is_symlink():
            raise OSError('model_nofollow')
        if flags & self.O_DIRECTORY:
            if self.fault == 'receive_open' and path.name.startswith('transfer-'):
                self.fail('receive_open')
            return self.directory(path)
        fd = os.open(path, (flags & ~(self.O_DIRECTORY | self.O_NOFOLLOW)) | getattr(os, 'O_BINARY', 0), mode)
        self.files[fd] = path
        self.flags[fd] = flags
        self.meta.setdefault(str(path), [mode if flags & os.O_CREAT else 0o600, 1001, 1002])
        self.events.append(('open', str(path.relative_to(self.root))))
        return fd

    def dup(self, fd):
        if fd in self.dirs:
            return self.directory(self.dirs[fd])
        new = os.dup(fd)
        self.files[new] = self.files[fd]
        self.flags[new] = self.flags[fd]
        return new

    def close(self, fd):
        if fd in self.dirs:
            del self.dirs[fd]
        else:
            self.files.pop(fd, None)
            self.flags.pop(fd, None)
            os.close(fd)
            if self.fault == 'close':
                self.fail('close')

    def metadata(self, path, actual):
        mode, uid, gid = self.meta.get(str(path), [0o700 if path.is_dir() else 0o600, 1001, 1002])
        fields = {key: getattr(actual, key) for key in ('st_dev', 'st_ino', 'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns')}
        # Windows may defer timestamp updates until close; Linux timestamp/race semantics are modeled.
        fields.update(st_mtime_ns=0, st_ctime_ns=0)
        return SimpleNamespace(**fields, st_mode=stat.S_IFMT(actual.st_mode) | mode, st_uid=uid, st_gid=gid)

    def fstat(self, fd):
        path = self.dirs.get(fd, self.files.get(fd))
        return self.metadata(path, os.stat(path) if fd in self.dirs else os.fstat(fd))

    def stat(self, name, *, dir_fd=None, follow_symlinks=False):
        path = self.path(name, dir_fd)
        return self.metadata(path, os.stat(path, follow_symlinks=follow_symlinks))

    def mkdir(self, name, mode, *, dir_fd=None):
        path = self.path(name, dir_fd)
        path.mkdir()
        self.meta[str(path)] = [mode, 1001, 1002]

    def write(self, fd, data):
        if fd == 2:
            self.events.append(('diagnostic', bytes(data).decode('ascii')))
            return len(data)
        self.events.append(('write', str(self.files[fd].relative_to(self.root))))
        if self.fault == 'partial':
            if os.lseek(fd, 0, os.SEEK_CUR) == 0:
                return os.write(fd, bytes(data[:3]))
            self.fail('partial')
        if self.fault == 'zero':
            self.events.append(('injected', 'zero'))
            return 0
        if self.fault == 'write':
            self.fail('write')
        return os.write(fd, bytes(data[:7]))  # Also exercises successful partial writes.

    def fchmod(self, fd, mode):
        if self.fault == 'chmod':
            self.fail('chmod')
        self.meta[str(self.files.get(fd, self.dirs.get(fd)))][0] = mode

    def fchown(self, fd, uid, gid):
        if self.fault == 'chown':
            self.fail('chown')
        path = self.files.get(fd, self.dirs.get(fd))
        self.meta.setdefault(str(path), [0o700 if path.is_dir() else 0o600, 1001, 1002])
        record = self.meta[str(path)]
        if uid != -1:
            record[1] = uid
        if gid != -1:
            record[2] = gid

    def fsync(self, fd):
        if fd in self.dirs:
            self.events.append(('directory_sync', str(self.dirs[fd].relative_to(self.root))))
            if self.fault == 'directory_sync' and any(e[0] == 'published' for e in self.events):
                self.fail('directory_sync')
        else:
            self.events.append(('file_sync', str(self.files[fd].relative_to(self.root))))
            if self.fault == 'file_sync':
                self.fail('file_sync')
            # Windows CRT rejects fsync on read-only descriptors; Linux replay sync is modeled.
            if self.flags[fd] & (os.O_WRONLY | os.O_RDWR):
                os.fsync(fd)

    def unlink(self, name, *, dir_fd=None):
        path = self.path(name, dir_fd)
        if self.cleanup_fault:
            self.fail('cleanup')
        path.unlink()
        self.meta.pop(str(path), None)

    def rename_noreplace(self, src, sn, dst, dn):
        source, destination = self.path(sn, src), self.path(dn, dst)
        if self.before_publish:
            self.before_publish(source, destination)
        if self.fault == 'publish':
            self.fail('publish')
        if destination.exists():
            raise FileExistsError('modeled_noreplace_race')
        # Windows rename refuses a destination; the Linux atomic primitive remains modeled.
        source.rename(destination)
        for path in list(self.meta):
            if path == str(source) or path.startswith(str(source) + os.sep):
                self.meta[str(destination) + path[len(str(source)):]] = self.meta.pop(path)
        self.events.append(('published', str(destination.relative_to(self.root))))

    def listdir(self, fd):
        return os.listdir(self.dirs[fd])

    def replace(self, source, destination, *, src_dir_fd=None, dst_dir_fd=None):
        before, after = self.path(source, src_dir_fd), self.path(destination, dst_dir_fd)
        os.replace(before, after)
        self.meta[str(after)] = self.meta.pop(str(before))

    def rmdir(self, name, *, dir_fd=None):
        path = self.path(name, dir_fd)
        if self.cleanup_fault:
            self.fail('cleanup')
        path.rmdir()
        self.meta.pop(str(path), None)

    def fstatvfs(self, fd):
        # Explicit free-space admission model; no installed filesystem/quota claim.
        return SimpleNamespace(f_bavail=1000000, f_frsize=4096)

    def tree(self, module, path):
        tree = module.Tree.__new__(module.Tree)
        tree.fd, tree.owner, tree.writable, tree.group = self.directory(path), 1001, True, 1002
        tree.device = self.fstat(tree.fd).st_dev
        return tree
