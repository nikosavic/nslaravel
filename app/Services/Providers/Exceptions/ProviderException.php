<?php

namespace App\Services\Providers\Exceptions;

use RuntimeException;

/** Expected, user-explainable provider failure (private profile, bad key, adapter not built yet...). */
class ProviderException extends RuntimeException
{
}
