<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

use Closure;
use Exception;
use Ultra\Data\Placeholder\Map;
use Ultra\Data\Query\Statement;

class Query {
	private const string PATTERN = '/(\{)? (\w+)? (
		(?<!\:|\}) \: (?!\:|\{) [abdfiknqsuvzABCDIKLNQSUV]? |
		(?<!\?|\}) \? (?!\?|\{) [abdinsuzABCDILNSU]? |
		(?<=\{)   \w+ (?=\})
	)(?(1)\})/ux';

	public readonly Map $map;
	public readonly Closure $booleans;
	public readonly string $start_quote;
	public readonly string $end_quote;
	private bool $_statement;
	private string $_query;

	public function __construct(
		public readonly Closure $escape,
		public readonly string $quantifier = '/^[^\W\d]([\w\.]*\w)?$/u',
		bool $booleans = false,
		string $quotes = '`',
	) {
		$this->map = new Map();
		$this->_statement = false;
		$this->_query = '';

		if ($booleans) {
			$this->booleans = fn($value) => $value ? 'TRUE' : 'FALSE';
		}
		else {
			$this->booleans = fn($value) => (string) (int) $value;
		}

		[$this->start_quote, $this->end_quote] = match(strlen($quotes)) {
			2 => str_split($quotes),
			1 => [$quotes, $quotes],
			default => ['', ''],
		};
	}

	public function statement(string $statement): void {
		$this->_make($statement);
	}

	public function list(string|int|float|bool|Closure|array|null ...$variables): string {
		return $this->_build($variables);
	}

	public function map(array $options): string {
		return $this->_build($options);
	}

	public function share(array $shared, string|int|float|bool|Closure|array|null ...$variables): string {
		//return $this->_buildShared1(array_merge([$shared], $variables));
		return $this->_build(array_merge([$shared, $shared], $variables));
	}

	public function join(array $options, int|string $common = 0, int|string $attached = 1): string {
		$this->_commonCheck($options, $common, $attached);
		return $this->_build(array_merge([$options[$common]], $options));
	}

	private function _commonCheck(array $options, int|string $common, int|string $attached): void {
		if (!isset($options[$common])) {
			throw new Exception('В запросе \''.$this->_query.'\' отсутствует разделяемое значение необходимые для заполненителей с индексами \''.$common.'\' и \''.$attached.'\'.');
		}

		if (!is_array($options[$common])) {
			throw new Exception('Неожиденный тип значения в запросе \''.$this->_query.'\'. Ожидался \'array\', получен \''.gettype($options[0]).'\'.');
		}
	}

	private function _build(array $vars): string {
		if (!$this->_statement) {
			return $this->_query;
		}

		return $this->_dropConditions($this->_fillPraceholders($this->_query, $vars));
	}

	private function _buildShared1(array $vars, int|string $common = 0, int|string $attached = 1): string {
		if (!$this->_statement) {
			return $this->_query;
		}

		$keys = array_flip($this->map->keys());

		if (!isset($keys[$common])) {
			throw new Exception('В запросе \''.$this->_query.'\' отсутствует заполненитель с индексом \''.$common.'\'.');
		}

		if (!isset($keys[1])) {
			throw new Exception('В запросе \''.$this->_query.'\' отсутствует заполненитель с индексом \''.$attached.'\'.');
		}

		$query = $this->_query;
		$query = $this->_assignPlaceholder($this->map->get($common), $query, $vars[$common]);
		$query = $this->_assignPlaceholder($this->map->get($attached), $query, $vars[$common]);
		unset($keys[$common], $keys[$attached]);
		
		$offset = 2;

		//$keys = array_map(fn($key) => $key + 2, $this->map->keys());

		return $this->_dropConditions($this->_fillPraceholders($this->_query, $vars));
	}


	private function _make(string $statement): void {
		if (0 == preg_match_all(self::PATTERN, $statement, $matches, PREG_OFFSET_CAPTURE)) {
			$this->_statement = false;
			$this->_query = $statement;
			return;
		}

		$sm = new Statement(
			holders:  array_map(fn($match) => $match[0], $matches[0]),
			captures: array_map(fn($match) => $match[1], $matches[0]),
			sequence: array_map(fn($match) => $match[0], $matches[2]),
			types:    array_map(fn($match) => $match[0], $matches[3]),
		);

		$this->_statement = true;
		$this->_query = $sm->buildQuery($statement);
		$sm->buildMap($this->map);
	}

	private function _dropConditions(string $query): string {
		if (str_contains($query, '[')) {
            return str_replace(['[', ']'], '', preg_replace('/\[[^]]*\{\w+\}[^]]*\]/', '', $query));
        }

		return $query;
	}

	private function _fillPraceholders(string $query, array $vars): string {
		$lack = [];

		foreach ($this->map->iterator() as $placeholder) {
			$id = $placeholder->id;

			if (isset($vars[$id])) {
				$query = $this->_assignPlaceholder($placeholder, $query, $vars[$id]);
			}
			elseif (!$placeholder->conditional) {
				$lack[] = '\''.$placeholder->index.$placeholder->type->value.'\'';
			}
		}

		if (isset($lack[0])) {
			throw new Exception('Отсутствуют необходимые для построения запроса \''.$this->_query.'\' значения заполненителей: '.implode(', ', $lack).'.');
		}

		return $query;
	}

	private function _assignPlaceholder(Placeholder $placeholder, string $query, string|int|float|bool|Closure|array|null $var): string {
		$placeholder->assign($this, $var);
		$query = str_replace($placeholder->search, $placeholder->value, $query);
		$placeholder->flush();
		return $query;
	}
}
