<?php
namespace Yale\Yes3Exporter2;

class Yes3K
{    
    public const YES3_MODULE_NAME = "Yes3Exporter";
    public const YES3_MODULE_PREFIX = "yes3_exporter";
    
    public const ONE_MINUTE = 60;
    public const ONE_HOUR = 60*60;
    public const ONE_DAY = 24*60*60;

    public const DATA_MANAGEMENT_ROLE_NAME  = "data";
    public const DATA_COLLECTION_ROLE_NAME  = "staff";

    public const STD_RETURN_OBJECT_FAIL = "fail";
    public const STD_RETURN_OBJECT_SUCCESS = "success";

    public const FORM_COMPLETION_COMPLETE = 2;
    public const FORM_COMPLETION_INCOMPLETE = 1;
    public const FORM_COMPLETION_NA = 9;
    public const FORM_COMPLETION_UNINITIALIZED = ".";

    public const SAS_LENGTH_MAX_VARNAME = 32; // SAS variable names are limited to 32 characters.
    public const SAS_LENGTH_MAX_LABEL = 256; // SAS variable labels are limited to 256 characters.
    public const SAS_LENGTH_MAX_FMTNAME = 30; // SAS format names are limited to 32 characters, INCLUDING the trailing period and the leading dollar sign, so we impose a 30-char limit.

    public const LOG_MESSAGE_FILES_WRITTEN = "export files written";
    public const LOG_MESSAGE_DD_DOWNLOADED = "export data dictionary downloaded";
    public const LOG_MESSAGE_DATA_DOWNLOADED = "export data downloaded";
    public const LOG_MESSAGE_ZIP_DOWNLOADED = "export zip downloaded";

    public const DESTINATION_DOWNLOAD = "download";
    public const DESTINATION_FILESYSTEM = "filesystem";
    public const DESTINATION_BATCH = "filesystem(batch)";  // user-requested batch filesystem export
    public const DESTINATION_CRON = "filesystem(cron)";  // cron batch export

    public const VARTYPE_TEXT = "TEXT";

    public const HASHED_VALUE_LENGTH = 32; // SHA-256 hash length
    public const MULTISELECT_DELIM = "___";

    public const MAX_LABEL_LEN = 1024; // max length of field label in the database

    public const VERY_LARGE_NUMBER = 9999999999; // used for min_value in calculations

    public const EXPORT_DATA_EXTENSION = ".tsv";
    public const EXPORT_DATA_DELIMITER = "\t"; // default delimiter for export data files




}