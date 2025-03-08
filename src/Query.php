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
	private string $_query;

	public function __construct(
		public readonly Closure $escape,
		public readonly string $quantifier = '/^[^\W\d]([\w\.]*\w)?$/u',
		bool $booleans = false,
		string $quotes = '`',

	) {
		$this->map = new Map();
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

	public function updateQuery(Placeholder $placeholder): void {
		if (null == $placeholder->value) {
			return;
		}

		$this->_query = str_replace($placeholder->search, $placeholder->value, $this->_query);
		$placeholder->flush();
	}

	public function list(string $statement, string|int|float|bool|Closure|array|null ...$variables): string {
		return $this->_buildQuery($statement, $variables);
	}

	public function map(string $statement, array $options): string {
		return $this->_buildQuery($statement, $options);
	}

	public function share(string $statement, array $shared, string|int|float|bool|Closure|array|null ...$variables): string {
		return $this->_buildQuery($statement, array_merge([$shared, $shared], $variables));
	}

	public function join(string $statement, array $options): string {
		$this->_shareCombineCheck($options);
		return $this->_buildQuery($statement, array_merge([$options[0]], $options));
	}

	private function _shareCombineCheck(array $options): void {
		if (!isset($options[0])) {
			throw new Exception('Отсутствует разделяемое значение необходимые для заполненителей с индексами 0 и 1.');
		}

		if (!is_array($options[0])) {
			throw new Exception('Неожиденный тип значения. Ожидался \'array\', получен \''.gettype($options[0]).'\'.');
		}
	}

	private function _buildQuery(string $statement, array $vars): string {
		if (!$this->_makeQuery($statement)) {
			return $statement;
		}

		$this->_fillPraceholders($vars);
		$this->_dropConditions();
		return $this->_query;
	}

	private function _makeQuery(string $statement): bool {
		if (0 == preg_match_all(self::PATTERN, $statement, $matches, PREG_OFFSET_CAPTURE)) {
			return false;
		}

		$sm = new Statement(
			holders:  array_map(fn($match) => $match[0], $matches[0]),
			captures: array_map(fn($match) => $match[1], $matches[0]),
			sequence: array_map(fn($match) => $match[0], $matches[2]),
			types:    array_map(fn($match) => $match[0], $matches[3]),
		);

		$this->_query = $sm->buildQuery($statement);
		$sm->buildMap($this->map);
		return true;
	}

	private function _dropConditions(): void {
		if (str_contains($this->_query, '[')) {
            $this->_query = str_replace(['[', ']'], '', preg_replace('/\[[^]]*\{\w+\}[^]]*\]/', '', $this->_query));
        }
	}

	private function _fillPraceholders(array $vars): void {
		$lack = [];

		foreach ($this->map->iterator() as $id => $holder) {
			if (isset($vars[$id])) {
				$holder->assign($this, $vars[$id]);
			}
			elseif (!$holder->conditional) {
				$lack[] = '\''.$holder->index.$holder->type->value.'\'';
			}
		}

		if (isset($lack[0])) {
			throw new Exception('Отсутствуют значения заполненителей '.implode(', ', $lack).', необходимые для построения запроса.');
		}
	}
}
