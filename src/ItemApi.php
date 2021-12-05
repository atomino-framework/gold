<?php

namespace Atomino\Gold;

use Atomino\Carbon\Entity;
use Atomino\Carbon\ValidationError;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use function Atomino\debug;

class ItemApi extends AbstractApi {

	const UPDATE = "update";
	const CREATE = "create";

	protected function export(Entity $item): array { return $item->export(); }
	protected function import(Entity $item, array $data, string $method): Entity { return $item->import($data); }
	/** @throws ValidationError */
	protected function update(Entity $item, array $data): int|null { return $this->import($item, $data, static::UPDATE)->save(); }
	/** @throws ValidationError */
	protected function create(Entity $item, array $data): int|null { return $this->import($item, $data, static::CREATE)->save(); }
	protected function delete(Entity $item) { $item->delete(); }
	protected function blank() { return ($this->entity)::create(); }
	protected function get(int|null $id): Entity|null {
		$item = ($this->entity)::pick($id);
		if (is_null($item)) $this->setStatusCode(404);
		return $item;
	}
	protected function options(Entity $item): array|null { return null; }

	#[Route("POST", '/get/:id([0-9]+)')]
	public final function POST_get(int $id): array|null {
		if (is_null($item = $this->get($id))) return null;
		$options = $this->options($item);
		if (is_null($options)) {
			$this->getResponse()->headers->add(["X-Gold-Form-Response-Type" => "basic"]);
			return $this->export($item);
		} else {
			$this->getResponse()->headers->add(["X-Gold-Form-Response-Type" => "complex"]);
			return [
				"options" => $options,
				"item"    => $this->export($item),
			];
		}
	}

	#[Route("POST", '/blank')]
	public final function POST_blank(): array {
		$item = $this->export($this->blank());
		if (is_null($options)) {
			$this->getResponse()->headers->add(["X-Gold-Form-Response-Type" => "basic"]);
			return $this->export($item);
		} else {
			$this->getResponse()->headers->add(["X-Gold-Form-Response-Type" => "complex"]);
			return [
				"options" => $options,
				"item"    => $this->export($item),
			];
		}
	}

	#[Route("POST", '/create')]
	public final function POST_create(): int|array {
		$data = $this->data->get("item");
		$item = ($this->entity)::create();
		try {
			$this->create($item, $data);
		} catch (ValidationError $e) {
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return $e->getMessages();
		}
		return $item->id;
	}

	#[Route("POST", '/update/:id([0-9]+)')]
	public final function POST_update(int $id): null|int|array {
		if (is_null($item = $this->get($id))) return null;
		$data = $this->data->get("item");
		try {
			$this->update($item, $data);
		} catch (ValidationError $e) {
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return $e->getMessages();
		}
		return $item->id;
	}

	#[Route("POST", '/delete/:id([0-9]+)')]
	public final function POST_delete(int $id) {
		if (is_null($item = $this->get($id))) return;
		try {
			$this->delete($item);
		} catch (\Throwable $exception) {
			debug("can not delete");
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return [["field" => "", "message" => "Can not delete the item"]];
		}
	}
}
