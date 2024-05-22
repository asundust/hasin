<?php

namespace BiiiiiigMonster\Hasin\Database\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use LogicException;

class RelationMixin
{
    public function getRelationExistenceInQuery(): Closure
    {
        return function (Builder $query, Builder $parentQuery, $columns = ['*']): Builder {
            $relation = function (Builder $query, Builder $parentQuery, $columns = ['*']): Builder {
                return $query->select($columns);
            };
            // basic builder
            $belongsTo = function (Builder $query, Builder $parentQuery) use ($relation): Builder {
                $columns = $query->qualifyColumn($this->ownerKey);

                $relationQuery = $relation($query, $parentQuery, $columns);

                if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
                    $relationQuery->from(
                        $query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash()
                    );

                    $relationQuery->getModel()->setTable($hash);
                }

                return $relationQuery;
            };
            $belongsToMany = function (Builder $query, Builder $parentQuery) use ($relation): Builder {
                $columns = $this->getExistenceCompareKey();
                if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
                    $query->select($columns);

                    $query->from($this->related->getTable().' as '.$hash = $this->getRelationCountHash());

                    $this->related->setTable($hash);
                }

                $this->performJoin($query);

                return $relation($query, $parentQuery, $columns);
            };
            $hasOneOrMany = function (Builder $query, Builder $parentQuery) use ($relation): Builder {
                $columns = $this->getExistenceCompareKey();
                if ($query->getQuery()->from == $parentQuery->getQuery()->from) {
                    $query->from($query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash());

                    $query->getModel()->setTable($hash);
                }

                return $relation($query, $parentQuery, $columns);
            };
            $hasManyThrough = function (Builder $query, Builder $parentQuery) use ($relation): Builder {
                $columns = $this->getQualifiedFirstKeyName();
                if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
                    $query->from($query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash());

                    $query->join($this->throughParent->getTable(), $this->getQualifiedParentKeyName(), '=', $hash.'.'.$this->secondKey);

                    if ($this->throughParentSoftDeletes()) {
                        $query->whereNull($this->throughParent->getQualifiedDeletedAtColumn());
                    }

                    $query->getModel()->setTable($hash);

                    return $relation($query, $parentQuery, $columns);
                }

                if ($parentQuery->getQuery()->from === $this->throughParent->getTable()) {
                    $table = $this->throughParent->getTable().' as '.$hash = $this->getRelationCountHash();

                    $query->join($table, $hash.'.'.$this->secondLocalKey, '=', $this->getQualifiedFarKeyName());

                    if ($this->throughParentSoftDeletes()) {
                        $query->whereNull($hash.'.'.$this->throughParent->getDeletedAtColumn());
                    }

                    return $relation($query, $parentQuery, $columns);
                }

                $this->performJoin($query);

                return $relation($query, $parentQuery, $columns);
            };
            // extended builder
            $hasOne = function (Builder $query, Builder $parentQuery) use ($hasOneOrMany): Builder {
                if ($this->isOneOfMany()) {
                    $this->mergeOneOfManyJoinsTo($query);
                }

                return $hasOneOrMany($query, $parentQuery);
            };
            $morphOneOrMany = function (Builder $query, Builder $parentQuery) use ($hasOneOrMany): Builder {
                return $hasOneOrMany($query, $parentQuery)->where(
                    $query->qualifyColumn($this->getMorphType()),
                    $this->morphClass
                );
            };
            $morphOne = function (Builder $query, Builder $parentQuery) use ($morphOneOrMany): Builder {
                if ($this->isOneOfMany()) {
                    $this->mergeOneOfManyJoinsTo($query);
                }

                return $morphOneOrMany($query, $parentQuery);
            };
            $morphToMany = function (Builder $query, Builder $parentQuery) use ($belongsToMany): Builder {
                return $belongsToMany($query, $parentQuery)->where(
                    $this->qualifyPivotColumn($this->morphType),
                    $this->morphClass
                );
            };

            switch (true) {
                // extended relation
                case $this instanceof HasOne:
                    return $hasOne($query, $parentQuery);
                case $this instanceof MorphOne:
                    return $morphOne($query, $parentQuery);
                case $this instanceof MorphOneOrMany:
                    return $morphOneOrMany($query, $parentQuery);
                case $this instanceof MorphToMany:
                    return $morphToMany($query, $parentQuery);
                // basic relation
                case $this instanceof BelongsTo:
                    return $belongsTo($query, $parentQuery);
                case $this instanceof BelongsToMany:
                    return $belongsToMany($query, $parentQuery);
                case $this instanceof HasOneOrMany:
                    return $hasOneOrMany($query, $parentQuery);
                case $this instanceof HasManyThrough:
                    return $hasManyThrough($query, $parentQuery);
                default:
                    throw new LogicException(
                        sprintf('%s must be a relationship instance.', get_class($this))
                    );
            }
        };
    }

    public function getRelationWhereInKey(): Closure
    {
        return function (): string {
            switch (true) {
                case $this instanceof BelongsTo:
                    return $this->getQualifiedForeignKeyName();
                case $this instanceof HasOneOrMany || $this instanceof BelongsToMany:
                    return $this->getQualifiedParentKeyName();
                case $this instanceof HasManyThrough:
                    return $this->getQualifiedLocalKeyName();
                default:
                    throw new LogicException(
                        sprintf('%s must be a relationship instance.', get_class($this))
                    );
            }
        };
    }
}
