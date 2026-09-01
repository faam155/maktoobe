<?php

namespace App\Enums;

enum PromptVisibility: string
{
    case Private = 'private';
    case SelectedRoles = 'selected_roles';
    case SelectedUsers = 'selected_users';
    case AllUsers = 'all_users';
}
