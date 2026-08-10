<?php

namespace App\Modules\Media\Domain;

enum MediaVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
