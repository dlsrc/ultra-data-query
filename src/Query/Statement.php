<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Query;

use Ultra\Data\Placeholder\Map;

class Statement {
	private array $holders;
	private array $captures;
	private array $sequence;
	private array $types;
	private array $explicit;
	private array $reference;

	public function __construct(array $holders, array $captures, array $sequence, array $types) {
		$this->holders   = $holders;
		$this->captures  = $captures;
		$this->sequence  = $sequence;
		$this->types     = $types;
		$this->explicit  = array_count_values(array_filter($sequence, fn($index) => '' != $index));
		$this->reference = array_filter(
			$types,
			fn($value) => !str_starts_with($value, '?') && !str_starts_with($value, ':')
		);
	}

	public function buildMap(Map $map): void {
		$map->clean();

		foreach (array_unique($this->sequence) as $key => $index) {
			$map->add($index, $this->types[$key], isset($this->captures[$index]));
		}
	}

	public function buildQuery(string $statement): string {
		$this->_indexSequence();

		return $this->_captureConditionlHolders(
			$this->_replaceQueryHolders($statement)
		);
	}

	private function _indexSequence(): void {
		$serial      = 0;
		$repetitions = [];

		// Индексирование главной последовательности заполнителей
		foreach ($this->sequence as $id => $index) {
			// Заполнители, обозначенные как индексы в самом запросе, не нуждаются в индексиловании.
			// Их нужно перенести в главную последовательность и временно указать тип по умолчанию.
			if (isset($this->reference[$id])) {
				$this->sequence[$id] = $this->types[$id];
				$this->types[$id] = '?';
				// Тип надо вычислить и обновить запись в $types[$i] позже,
				// когда будут известны все типы заполнитеоей в последовательности.
				continue;
			}
			if (!isset($this->explicit[$index])) {
				while (isset($this->explicit[$serial])) {
					$serial++;
				}

				//$this->sequence[$id] = $serial++;
				$this->sequence[$id] = (string) $serial++;
			}
			elseif ($this->explicit[$index] > 1) {
				$type = $this->types[$id];

				if (!isset($repetitions[$index])) { // -> Выявление неожиданной смены типа заполнителя
					$discord = array_reduce(array_keys(array_filter($this->sequence, fn($alias) => $alias == $index)),
						fn($carry, $item) => $carry ??= ($type != $this->types[$item]) ? $this->types[$item] : null
					);

					if ($discord) {
						exit($discord);
					}
				
					$repetitions[$index] = $type;
				} // <- Выявление неожиданной смены типа заполнителя
			}
		}
	}

	private function _replaceQueryHolders(string $statement): string {
		// Замена заполнителей индексами.
		for ($i = array_key_last($this->sequence); $i >= 0; $i--) {
			if (isset($this->reference[$i])) {
				// Индексы, присутствовавшие в запросе изначально, заменять не надо,
				// но нужно уточнить тип индексированного заполнителя.
				$this->_specifyReferenceType($i);
				continue;
			}
			// Замена заполнителя на индекс последовательности
			$statement = substr_replace(
				$statement,
				'{'.$this->sequence[$i].'}',
				(int) $this->captures[$i],
				strlen($this->holders[$i])
			);
		}

		return str_replace(['::', '??'], [':', '?'], $statement);
	}

	private function _specifyReferenceType(int $ref_id): void {
		foreach ($this->sequence as $id => $value) {
			if ($ref_id != $id && $this->reference[$ref_id] == $value) {
				$this->types[$ref_id] = $this->types[$id];
				break;
			}
		}
	}

	private function _captureConditionlHolders(string $statement): string {
		// Вычисление количества вставок для каждого индекса в полной последовательности
		$this->holders = array_count_values($this->sequence);
		// Поиск условных блоков в запросе и получение списка индексов заполнителей,
		// которые не требуют обязательного наличия значения.
		if (preg_match_all('/\[[^\]]+\]/', $statement, $submatch) > 0) {
			$counts = [];
		
			foreach ($submatch[0] as $subquery) {
				if (preg_match_all('/\{(\w+)\}/', $subquery, $subindex) > 0) {
					foreach ($subindex[1] as $index) {
						if (isset($counts[$index])) {
							$counts[$index]++;
						}
						else {
							$counts[$index] = 1;
						}
					}
				}
			}
			// В условный контекст попадают только те индексы условных блоков,
			// количество которых совпадает с их количеством в полной последовательности.
			$this->captures = array_filter(
				$counts,
				fn($index, $key) => $index == $this->holders[$key],
				ARRAY_FILTER_USE_BOTH
			);
		}
		else {
			$this->captures = [];
		}

		return $statement;
	}
}
