<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Placeholder;

use Generator;
use Ultra\Data\Placeholder;

class Map {
	private array $_placeholders;

	public function __construct(){
		$this->_placeholders = [];
	}

	public function add(string $index, string $type, bool $conditional): void {
		if (isset($this->_placeholders[$index])) {
			return;
		}

		if (!$placeholder = Type::tryFrom($type)) {
			exit('Error Placeholder type.');
		}

		$this->_placeholders[$index] = new Placeholder($index, $placeholder, $conditional);
	}

	public function clean(): void {
		$this->_placeholders = [];
	}

	public function iterator(array|null $keys = null): Generator {
		if (null === $keys) {
			$keys = array_keys($this->_placeholders);
		}
		
		foreach ($keys as $id) {
			yield $id => $this->_placeholders[$id];
		}
	}

	public function keys(): array {
		return array_keys($this->_placeholders);
	}

	public function get(int|string $id): Placeholder|null {
		if (isset($this->_placeholders[$id])) {
			return $this->_placeholders[$id];
		}

		return null;
	}
}