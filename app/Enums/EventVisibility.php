<?php

namespace App\Enums;

enum EventVisibility: string
{
    case AllUsers = 'all_users';
    case Private = 'private';
    case SelectedUsers = 'selected_users';
    case SelectedRoles = 'selected_roles';
}
