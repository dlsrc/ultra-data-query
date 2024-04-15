<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\MySQL;

use Ultra\Data\Statement as DataStatement;

final class Statement extends DataStatement {
	public function showTables(string $table_like = '', string $schema = '', string $dbname = ''): string {
		$sql = 'SHOW TABLES';

		if ('' != $dbname) {
			$sql .= ' FROM '.$this->_property['id_quote'].
			$this->contract->driver->escape($this->contract->connector, $dbname).
			$this->_property['id_quote'];
		}

		if ('' != $table_like) {
			if (str_starts_with($table_like, '%') || str_ends_with($table_like, '%')) {
				$sql .= ' LIKE "'.$table_like.'"';
			}
			else {
				$sql .= ' LIKE "%'.$table_like.'%"';
			}
		}

		return $sql;
	}

	protected function initialize(): void {
		$this->_property['current_date']        = 'CURRENT_DATE';
		$this->_property['current_time']        = 'CURRENT_TIME';
		$this->_property['current_timestamp']   = 'NOW()';
		$this->_property['last_insert_id']      = 'LAST_INSERT_ID()';
		$this->_property['id_quote']            = '`';
		$this->_property['boolean_supported']   = false;
		$this->_property['insert_ignore_start'] = 'INSERT IGNORE INTO ';
		$this->_property['insert_ignore_end']   = '';
	}
}
