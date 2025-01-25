<?php

namespace OPodSync;

/**
 * ENABLE_SUBSCRIPTIONS
 * true = Open subscriptions to anyone
 * false = Subscriptions are closed after the first account is created
 *
 * Default: false
 * @var bool
 */
const ENABLE_SUBSCRIPTIONS = false;


/**
 * TITLE
 * This is used for the instance name
 *
 * Default: My oPodSync server
 * @var string
 */
const TITLE = 'My oPodSync server';

/**
 * BASE_URL
 * Set to the URL where the server is hosted
 *
 * Default: detected automatically if not set (can fail in some cases)
 * @var string
 */
//const BASE_URL = 'https://gpodder.mydomain.tld/me/';

/**
 * DISABLE_USER_METADATA_UPDATE
 * Set this to TRUE to forbid users from updating feed metadata,
 * as this may add some load on your server
 * (default is FALSE)
 *
 * @var bool
 */
const DISABLE_USER_METADATA_UPDATE = false;

/**
 * DATA_ROOT
 * Path of directory where data will be stored.
 * ROOT is the root of oPodSync code.
 *
 * @var string
 */
const DATA_ROOT = ROOT . '/data';

/**
 * CACHE_ROOT
 * Path of directory where cache will be stored.
 * ROOT is the root of oPodSync code.
 *
 * @var string
 */
const CACHE_ROOT = DATA_ROOT . '/cache';

/**
 * DB_FILE
 * SQLite3 database file
 * This is where the users, app sessions and stuff will be stored
 */
const DB_FILE = DATA_ROOT . '/data.sqlite';

/**
 * SQLITE_JOURNAL_MODE
 * SQLite3 journaling mode
 * Default: TRUNCATE (slower)
 * Recommended: WAL (faster, but read below)
 *
 * If your database file is on a local disk, you will get better performance by using
 * 'WAL' journaling instead. But it is not enabled by default as it may
 * lead to database corruption on some network storage (eg. old NFS).
 *
 * @see https://www.sqlite.org/pragma.html#pragma_journal_mode
 * @see https://www.sqlite.org/wal.html
 * @see https://stackoverflow.com/questions/52378361/which-nfs-implementation-is-safe-for-sqlite-database-accessed-by-multiple-proces
 *
 * Default: 'TRUNCATE'
 * @var string
 */
const SQLITE_JOURNAL_MODE = 'TRUNCATE';

/**
 * ERRORS_SHOW
 * Show PHP errors details to users?
 * If set to TRUE, full error messages and source code will be displayed to visitors.
 * If set to FALSE, just a generic "an error happened" message will be displayed.
 *
 * It is recommended to set this to FALSE in production.
 * Default: TRUE
 *
 * @var bool
 */
const ERRORS_SHOW = true;

/**
 * ERRORS_EMAIL
 * Send PHP errors to this email address
 * The email will contain
 * Default: NULL (errors are not sent by email)
 *
 * @var string|null
 */
const ERRORS_EMAIL = null;

/**
 * ERRORS_LOG
 * Log PHP errors in this file.
 * Default: ROOT/data/error.log
 *
 * @var string
 */
const ERRORS_LOG = DATA_ROOT . '/error.log';

/**
 * ERRORS_REPORT_URL
 * Send errors reports to this errbit/airbrake compatible API endpoint
 * Default: NULL
 * Example: 'https://user:password@domain.tld/errors'
 *
 * @var string|null
 * @see https://errbit.com/images/error_summary.png
 * @see https://airbrake.io/docs/api/#create-notice-v3
 */
const ERRORS_REPORT_URL = null;

/**
 * DEBUG_LOG
 * Log API calls to this file.
 * Useful for development.
 *
 * Default: null (= disabled)
 * @var string|null
 */