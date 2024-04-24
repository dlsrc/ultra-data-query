<?php declare(strict_types=1);
/**
 * (c) 2005-2024 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

use Ultra\Fail;
use Ultra\Result;
use Ultra\State;

final class Insert {
	private const string FIELD_PATTERN = '/^(\w[\w\.]*?\w?)(?::((?:\?|)(?:i|d|b|s)(?:\.|)))?$/';

	public readonly Storage $contract;
	private string $_table;
	private array $_fields;
	private array $_types;
	private array $_values;
	private bool $_boolean;
	private int $_count;
	private Fail|null $_error;

	public function __construct(Storage $contract, string $table, string ...$fields) {
		$this->contract  = $contract;
		$this->_table    = '';
		$this->_fields   = [];
		$this->_types    = [];
		$this->_values   = [];
		$this->_boolean  = false;
		$this->_count    = 0;
		$this->_error    = null;
		$this->names($table, $fields);
	}

	private function isError(): bool {
		if (null == $this->_error) {
			return false;
		}

		return true;
	}

	private function names(string $table, array $fields): void {
		if (empty($fields)) {
			return;
		}

		$this->_table = $table;
		
		foreach ($fields as $field) {
			if (0 == preg_match(self::FIELD_PATTERN, $field, $match)) {
				$this->_error = new Fail(
					Status::QueryFailed,
					'The string "'.$field.'" cannot be an SQL table field identifier.',
					__FILE__,
					__LINE__-5,
				);

				return;
			}
			
			$this->_fields[] = $match[1];

			if (!isset($match[2])) {
				$this->_types[] = Insertion::Auto;
			}
			else {
				$this->_types[] = Insertion::get($match[2]);
			}
		}

		$this->_count = count($this->_fields);
	}

	private function prepare(array $values): bool {
		if (count($values) != $this->_count) {
			$this->_error = new Fail(
				Status::QueryFailed,
				'The number of values does not match the number of fields.',
				__FILE__,
				__LINE__-5,
			);

			return false;
		}

		foreach ($this->_types as $key => $type) {
			if (!$this->valueStringByType($type, $this->_fields[$key], $values[$key])) {
				return false;
			}
		}

		$this->_values[] = '('.implode(', ', $values).')';
		return true;
	}

	private function valueStringByType(Insertion $type, string $field,  mixed &$value): bool {
		$expected   = $type->baseType();
		$value_type = gettype($value);

		switch($value_type) {
		case 'string':
			if ($type->isString() || $type->isAuto()) {
				$value = '\''.$this->contract->driver->escape($this->contract->connector, $value).'\'';
				return true;
			}

			if ($type->isStrict()) {
				$this->_error = new Fail(
					Status::QueryFailed,
					'Type mismatch in the "'.$field.'" field. Expected strict '.$type->baseName().', String given.',
					__FILE__,
					__LINE__-5,
				);

				return false;
			}
				
			if (Insertion::Integer == $expected) {
				$prepare = (int) $value;

				if ($prepare == $value) {
					return true;
				}

				$this->_error = new Fail(
					Status::QueryFailed,
					'It is not possible to correctly cast the string value "'.$value.'" to type Integer.',
					__FILE__,
					__LINE__-9,
				);

				return false;
			}

			if (Insertion::Double == $expected) {
				$prepare = (float) $value;

				if ($prepare == $value) {
					return true;
				}

				$this->_error = new Fail(
					Status::QueryFailed,
					'It is not possible to correctly cast the string value "'.$value.'" to type Double.',
					__FILE__,
					__LINE__-9,
				);

				return false;
			}

			if (Insertion::Boolean == $expected) {
				$prepare = (bool) (int) $value;

				if ($this->_boolean) {
					$value = $prepare ? 'TRUE' : 'FALSE';
				}
				else {
					$value = (string) (int) $value;
				}

				return true;
			}

			break;

		case 'integer':
			if ($type->isInteger() || $type->isDouble() || $type->isAuto()) {
				$value = (string) $value;
				return true;
			}

			if ($type->isStrict()) {
				$this->_error = new Fail(
					Status::QueryFailed,
					'Type mismatch in the "'.$field.'" field. Expected strict '.$type->baseName().', Integer given.',
					__FILE__,
					__LINE__-5,
				);

				return false;
			}

			if (Insertion::String == $expected) {
				$value = '\''.$value.'\'';
				return true;
			}

			if (Insertion::Boolean == $expected) {
				$prepare = (bool) $value;

				if ($this->_boolean) {
					$value = $prepare ? 'TRUE' : 'FALSE';
				}
				else {
					$value = (string) (int) $value;
				}

				return true;
			}

			break;

		case 'double':
			if ($type->isDouble() || $type->isAuto()) {
				$value = (string) $value;
				return true;
			}

			if ($type->isStrict()) {
				$this->_error = new Fail(
					Status::QueryFailed,
					'Type mismatch in the "'.$field.'" field. Expected strict '.$type->baseName().', Double given.',
					__FILE__,
					__LINE__-5,
				);

				return false;
			}

			if (Insertion::String == $expected) {
				$value = '\''.$value.'\'';
				return true;
			}

			if (Insertion::Boolean == $expected) {
				$prepare = (bool) $value;

				if ($this->_boolean) {
					$value = $prepare ? 'TRUE' : 'FALSE';
				}
				else {
					$value = (string) (int) $value;
				}

				return true;
			}

			break;

		case 'boolean':
			if ($type->isBoolean()) {
				if ($this->_boolean) {
					$value = $value ? 'TRUE' : 'FALSE';
				}
				else {
					$value = $value ? '1' : '0';
				}

				return true;
			}
				
			if ($type->isStrict()) {
				$this->_error = new Fail(
					Status::QueryFailed,
					'Type mismatch in the "'.$field.'" field. Expected strict '.$type->baseName().', Boolean given.',
					__FILE__,
					__LINE__-5,
				);

				return false;
			}

			if (Insertion::String == $expected) {
				$value = '\''.(int) $value.'\'';
				return true;
			}

			if (Insertion::Integer == $expected) {
				$value = (int) $value;
				return true;
			}

			if (Insertion::Double == $expected) {
				$value = (float) $value;
				return true;
			}

			break;

		case 'NULL':
			if ($type->isNullable()) {
				$value = 'NULL';
				return true;
			}

			$this->_error = new Fail(
				Status::QueryFailed,
				'The "'.$field.'" field cannot be NULL.',
				__FILE__,
				__LINE__-9,
			);

			break;

		default:
			$this->_error = new Fail(
				Status::QueryFailed,
				'Unsupported value type "'.$value_type.'".',
				__FILE__,
				__LINE__-5,
			);
		}

		return false;
	}

	public function row(int|float|bool|string|null ...$values): void {
		if ($this->isError()) {
			return;
		}

		$this->prepare($values);
	}

	public function rows(array ...$rows): void {
		if ($this->isError()) {
			return;
		}

		foreach ($rows as $values) {
			if (!$this->prepare(array_values($values))) {
				return;
			}
		}
	}

	public function assoc(array ...$assoc_rows): void {
		if ($this->isError()) {
			return;
		}

		foreach ($assoc_rows as $assoc_row) {
			$values = [];

			foreach ($this->_fields as $field) {
				if (!isset($assoc_row[$field])) {
					$this->_error = new Fail(
						Status::QueryFailed,
						'The value for the "'.$field.'" field was not passed.',
						__FILE__,
						__LINE__-5,
					);

					return;
				}

				$values[] = $assoc_row[$field];
			}

			if (!$this->prepare($values)) {
				return;
			}
		}
	}

	public function useBoolean(): void {
		$this->_boolean = Statement::get($this->contract)->boolean_supported;
	}

	public function build(bool $ignore = true): State {
		if ($this->isError()) {
			return $this->_error;
		}

		$s = Statement::get($this->contract);
		$q = $s->id_quote;

		$sql = $q.$this->_table.$q.' ('.$q.implode($q.', '.$q, $this->_fields).$q.') '.
		'VALUES '.implode(', ', $this->_values);

		if ($ignore) {
			return new Result($s->insert_ignore_start.' '.$sql.$s->insert_ignore_end);
		}
		else {
			return new Result('INSERT INTO '.$sql);
		}
	}
}
