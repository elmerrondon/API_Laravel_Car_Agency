<?php

declare(strict_types=1);

namespace App\Enums\Audit;

enum AuditAction: string
{
    // Autenticación
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGIN_FAILED = 'auth.login_failed';
    case AUTH_LOGOUT = 'auth.logout';
    case AUTH_TOKEN_REFRESHED = 'auth.token_refreshed';

    // Usuarios y seguridad
    case USER_CREATED = 'user.created';
    case USER_UPDATED = 'user.updated';
    case USER_DELETED = 'user.deleted';
    case USER_ACTIVATED = 'user.activated';
    case USER_DEACTIVATED = 'user.deactivated';
    case USER_ROLES_CHANGED = 'user.roles_changed';
    case USER_PASSWORD_CHANGED = 'user.password_changed';
    case USER_PASSWORD_RESET = 'user.password_reset';
    case ROLE_CREATED = 'role.created';
    case ROLE_UPDATED = 'role.updated';
    case ROLE_DELETED = 'role.deleted';

    // Operaciones de negocio
    case CAR_CREATED = 'car.created';
    case CAR_UPDATED = 'car.updated';
    case CAR_DELETED = 'car.deleted';
    case BRANCH_CREATED = 'branch.created';
    case BRANCH_UPDATED = 'branch.updated';
    case BRANCH_DELETED = 'branch.deleted';
    case SALE_CREATED = 'sale.created';
    case SALE_CANCELLED = 'sale.cancelled';
}
