<?php

// Internal module calls remain in-process. This file is deliberately not loaded
// by the public API router or gateway during the compatibility split.
return static function (): void {
};
