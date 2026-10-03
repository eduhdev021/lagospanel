<?php

namespace App\Provisioning;

/** Only locally authored, non-secret messages may use this exception. */
final class ProtocolError extends \RuntimeException {}
