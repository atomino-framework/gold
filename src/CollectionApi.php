<?php namespace Atomino\Gold;

use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Attributes\Route;

class CollectionApi extends AbstractApi {

	public $order = [["id","ASC"]];

	protected function filter(string $search, int|null $contextId = null): Filter { return Filter::where("id=$1", $search); }
	protected function label(Entity $item): string { return $item->id; }

	#[Route("POST", "/search")]
	public final function POST_search(): array {
		$id = $this->data->get("id", null);
		$search = $this->data->get("search");
		$items = ($this->entity)::search($this->filter($search, $id))->order(...$this->order)->collect();
		$result = [];
		foreach ($items as $item) $result[] = ["value"=>$item->id, "label"=>$this->label($item)];
		return $result;
	}

	#[Route("POST", "/get")]
	public final function POST_get(): array {
		$value = $this->data->get("value");
		$items = ($this->entity)::search(Filter::where(($this->entity)::id($value)))->order(...$this->order)->collect();
		$result = [];
		foreach ($items as $item) $result[] = ["value"=>$item->id, "label"=>$this->label($item)];
		return $result;
	}

}
