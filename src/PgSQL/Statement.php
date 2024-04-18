<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\PgSQL;

use Ultra\Data\Statement as DataStatement;

final class Statement extends DataStatement {
	public function showTables(string $table_like = '', string $schema = '', string $dbname = ''): string {
		if ('' == $schema) {
			$schema = $this->contract->connector->getConfig()->schema;
		}

		return 'SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname LIKE \''.$schema.'\'';
	}

	protected function initialize(): void {
		$this->_property['current_date']        = 'CURRENT_DATE';
		$this->_property['current_time']        = 'CURRENT_TIME';
		$this->_property['current_timestamp']   = 'CURRENT_TIMESTAMP';
		$this->_property['last_insert_id']      = 'lastval()';
		$this->_property['id_quote']            = '"';
		$this->_property['boolean_supported']   = true;
		$this->_property['insert_ignore_start'] = 'INSERT INTO ';
		$this->_property['insert_ignore_end']   = ' ON CONFLICT DO NOTHING';
	}
}
