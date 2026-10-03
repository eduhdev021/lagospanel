<?php

return ['account_attachment_bytes' => max(10, min(1024, (int) env('SUPPORT_ATTACHMENT_ACCOUNT_MIB', 50))) * 1048576];
