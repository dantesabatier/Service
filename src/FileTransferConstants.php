<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Environment variable key for the comma-separated list of subdirectories that accept uploads and serve downloads. Unset means every syntactically valid single component is accepted. */
const FileTransferDirectoriesKey = "FILE_TRANSFER_DIRECTORIES";
/** @var string Environment variable key for the comma-separated list of accepted upload file extensions, without the leading dot. Unset means the extension is not restricted. */
const FileTransferAllowedExtensionsKey = "FILE_TRANSFER_ALLOWED_EXTENSIONS";
/** @var string Environment variable key for the largest accepted upload size in bytes. */
const FileTransferMaximumSizeKey = "FILE_TRANSFER_MAXIMUM_SIZE";
/** @var string Environment variable key for the octal POSIX permissions applied to an uploaded file, for example `0666`. */
const FileTransferFilePermissionsKey = "FILE_TRANSFER_FILE_PERMISSIONS";
/** @var int Default largest accepted upload size in bytes. Zero means the framework imposes no limit of its own, leaving the transport's `upload_max_filesize` as the only ceiling. */
const FileTransferMaximumSizeDefault = 0;
/** @var int Default POSIX permissions applied to an uploaded file and to the directory created for it. Deliberately permissive: a deployment where the writing process and the reading one are different users cannot read back a stricter file, and the endpoint that serves it is mediated by the responder chain rather than by the filesystem. Tighten it through FILE_TRANSFER_FILE_PERMISSIONS where the deployment allows. */
const FileTransferFilePermissionsDefault = 0777;
