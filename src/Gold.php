<?php namespace Atomino\Gold;

use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Gold extends Attr {
	public function __construct(public string $class, public int $pagesize, public bool $quicksearch) { }
}
