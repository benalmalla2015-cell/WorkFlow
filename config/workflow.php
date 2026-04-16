<?php

return [
    'uploads_disk' => env('WORKFLOW_UPLOADS_DISK', 'public'),
    'documents_disk' => env('WORKFLOW_DOCUMENTS_DISK', 'public'),
    'templates_disk' => env('WORKFLOW_TEMPLATES_DISK', 'local'),
    'backup_disk' => env('WORKFLOW_BACKUP_DISK', 'local'),
    'sales_upload_root' => env('WORKFLOW_SALES_UPLOAD_ROOT', 'sales_uploads'),
    'factory_upload_root' => env('WORKFLOW_FACTORY_UPLOAD_ROOT', 'factory_uploads'),
    'quotations_root' => env('WORKFLOW_QUOTATIONS_ROOT', 'quotations'),
    'invoices_root' => env('WORKFLOW_INVOICES_ROOT', 'invoices'),
    'templates_root' => env('WORKFLOW_TEMPLATES_ROOT', 'templates'),
    'word_template_name' => env('WORKFLOW_WORD_TEMPLATE_NAME', 'workflow_template.docx'),
    'excel_template_name' => env('WORKFLOW_EXCEL_TEMPLATE_NAME', 'workflow_template.xlsx'),
    'backup_root' => env('WORKFLOW_BACKUP_ROOT', 'database_backups'),
];
