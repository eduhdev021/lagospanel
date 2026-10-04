<?php

return ['sla' => ['low' => 48, 'normal' => 24, 'high' => 8, 'urgent' => 2], 'account_attachment_mib' => max(10, min(1024, (int) env('SUPPORT_ATTACHMENT_ACCOUNT_MIB', 50))), 'account_attachment_bytes' => max(10, min(1024, (int) env('SUPPORT_ATTACHMENT_ACCOUNT_MIB', 50))) * 1048576];
