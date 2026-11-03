<?php

declare(strict_types=1);

namespace App\Http\Resources\Users;

use App\Http\Resources\Locations\BranchResource;
use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'document_number' => $this->document_number,
            'code' => $this->code,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'permissions' => $this->permissionNames(),
        ];
    }

    /**
     * Permisos efectivos como lista plana y ordenada, calculados sobre las
     * relaciones ya cargadas para no repetir el join de la caché.
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        /** @var User $user */
        $user = $this->resource;

        if (! $user->relationLoaded('roles')) {
            return [];
        }

        /** @var Collection<int, Role> $roles */
        $roles = $user->roles;

        $names = [];

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $names[] = $permission->name->value;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
