<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\SQLite;

use Ultra\Data\Statement as DataStatement;

final class Statement extends DataStatement {
	public function showTables(string $table_like = '', string $schema = '', string $dbname = ''): string {
		if ('' == $table_like) {
			return 'SELECT name FROM sqlite_schema WHERE  type = \'table\'';
		}

		if (str_starts_with($table_like, '%') || str_ends_with($table_like, '%')) {
			return 'SELECT name FROM sqlite_schema WHERE  type = \'table\' AND name LIKE \''.$table_like.'\'';
		}

		return 'SELECT name FROM sqlite_schema WHERE  type = \'table\' AND name LIKE \'%'.$table_like.'%\'';
	}

	protected function initialize(): void {
		$this->_property['current_date']        = 'date(\'now\')';
		$this->_property['current_time']        = 'time(\'now\')';
		$this->_property['current_timestamp']   = 'datetime(\'now\')';
		$this->_property['last_insert_id']      = 'last_insert_rowid()';
		$this->_property['id_quote']            = '`';
		$this->_property['boolean_supported']   = false;
		$this->_property['insert_ignore_start'] = 'INSERT OR IGNORE INTO ';
		$this->_property['insert_ignore_end']   = '';
	}
}
