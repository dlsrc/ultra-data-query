<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

use Ultra\Generic\Getter;

abstract class Statement {
	use Getter;

	abstract public function showTables(string $table_like = '', string $schema = '', string $dbname = ''): string;

	final public static function get(Browser $contract): static {
		return match($contract->driver::class) {
			namespace\MySQL\Driver::class  => new namespace\MySQL\Statement($contract),
			namespace\PgSQL\Driver::class  => new namespace\PgSQL\Statement($contract),
			namespace\SQLite\Driver::class => new namespace\SQLite\Statement($contract),
		};
	}

	final protected function __construct(public readonly Browser $contract) {
		$this->initialize();
	}
}