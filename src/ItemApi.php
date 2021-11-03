<?php

namespace Atomino\Gold;

use Atomino\Carbon\Entity;
use Atomino\Carbon\ValidationError;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use function Atomino\debug;

class ItemApi extends AbstractApi {

	protected function export(Entity $item): array { return $item->export(); }
	/** @throws ValidationError */
	protected function update(Entity $item, array $data): int|null { return $item->import($data)->save(); }
	/** @throws ValidationError */
	protected function create(Entity $item, array $data): int|null { return $item->import($data)->save(); }
	protected function delete(Entity $item) { $item->delete(); }
	protected function blank() { return ($this->entity)::create(); }
	protected function get(int|null $id): Entity|null {
		$item = ($this->entity)::pick($id);
		if (is_null($item)) $this->setStatusCode(404);
		return $item;
	}

	#[Route("POST", '/get/:id([0-9]+)')]
	public final function POST_get(int $id): array|null {
		if (is_null($item = $this->get($id))) return null;
		return $this->export($item);
	}

	#[Route("POST", '/blank')]
	public final function POST_blank(): array {
		return $this->export($this->blank());
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
