<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Yaml\Yaml;

/**
 * One version of the organization's IR plan. The newest version is the current plan.
 */
class IrPlan extends Model
{
    /**
     * @return HasMany<PlanSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PlanSection::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('version')->first();
    }

    /**
     * The organization profile as placeholder values, for example ['org.role.ciso.name' => 'Pat Example'].
     *
     * @return array<string, string>
     */
    public function profileValues(): array
    {
        $data = $this->profile ? Yaml::parse($this->profile) : null;

        return is_array($data) ? self::flatten($data, 'org') : [];
    }

    /**
     * The plan concepts that sections of this plan have, for example severity_table.
     *
     * @return list<string>
     */
    public function concepts(): array
    {
        return $this->sections->pluck('concept')->filter()->unique()->values()->all();
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix): array
    {
        $values = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $values += self::flatten($value, $prefix.'.'.$key);
            } elseif ($value !== null && $value !== '') {
                $values[$prefix.'.'.$key] = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            }
        }

        return $values;
    }
}
