<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Query;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

// Craft registers these macros when its service providers boot. Registering them here lets PHPStan
// resolve them even when Larastan can’t boot the app, as in the shared CI workflow.
Builder::macro('whereParam', fn (string|Expression $column, mixed $value, string $defaultOperator = '=', bool $caseInsensitive = false, ?string $columnType = null, string $boolean = 'and'): Builder => Query::whereParam($this, $column, $value, $defaultOperator, $caseInsensitive, $columnType, $boolean));
Builder::macro('whereNumericParam', fn (string|Expression $column, mixed $value, string $defaultOperator = '=', ?string $columnType = Query::TYPE_INTEGER, string $boolean = 'and'): Builder => Query::whereNumericParam($this, $column, $value, $defaultOperator, $columnType, $boolean));
Builder::macro('whereBool', fn (string $column, bool $value): Builder => $this);
Request::macro('getSigned', fn (string $key, mixed $default = null): mixed => $default);
