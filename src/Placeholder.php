<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

use Closure;
use Exception;
use Ultra\Data\Placeholder\Type;

class Placeholder {
	public readonly string $index;
	public readonly Type $type;
	public readonly bool $conditional;
	public private(set) string|null $value;
	public string $search {
		get => '{'.$this->index.'}';
	}

	public function __construct(string $index, Type $type, bool $conditional) {
		$this->index = $index;
		$this->type = $type;
		$this->conditional = $conditional;
		$this->value = null;
	}

	public function assign(Query $query, string|int|float|bool|Closure|array|null $var): void {
		if (is_object($var) && $var instanceof Closure) {
			$var = $var();
		}

		if (is_array($var)) {
			if ($this->type->needMap()) {
				$this->_fromMap($query, $var);
			}
			elseif ($this->type->needList()) {
				$this->_fromList($query, $var);
			}
			elseif ($this->type->needKeys()) {
				$this->_fromKeys($query, $var);
			}
			elseif ($this->type->needValues()) {
				$this->_fromValues($query, $var);
			}
			else {
				throw new Exception('Значение не соответствует типу заполнителя');
			}
		}
		else {
			$this->value = $this->_setVar($query, $var);
		}
	}

	public function flush(): void {
		$this->value = null;
	}

	private function _fromMap(Query $query, array $map): void {
		$quote_fn = $this->type->fnQuote($query);
		$var = [];

		foreach ($map as $q => $s) {
			$var[] = $quote_fn($q).' = '.$this->_setVar($query, $s);
		}

		$this->value = implode(', ', $var);
	}

	private function _fromKeys(Query $query, array $list): void {
		$quote_fn = $this->type->fnQuote($query);
		$this->value = implode(', ', array_map(fn($q) => $quote_fn($q), array_keys($list)));
	}

	private function _fromValues(Query $query, array $list): void {
		$quote_fn = $this->type->fnQuote($query);
		$this->value = implode(', ', array_map(fn($q) => $quote_fn($q), $list));
	}

	private function _fromList(Query $query, array $list): void {
		$this->value = implode(', ', array_map(fn($s) => $this->_setVar($query, $s), $list));
	}

	private function _setVar(Query $query, mixed $value): string {
		return match(gettype($value)) {
			'string'  => $this->type->fromString($query, $this, $value),
			'integer',
			'double'  => $this->type->fromNumeric($query, $this, $value),
			'boolean' => $this->type->fromBoolean($query, $this, $value),
			'NULL'    => $this->type->fromNull($query, $this),
			default   => throw new Exception('Unexpected parameter value type '.gettype($value).'.'),
		};
	}
}