<?php

namespace App\Central\Modules;

use LogicException;

/** A module change that is not allowed; the message is shown to the user as is. */
class ModuleActivationException extends LogicException {}
