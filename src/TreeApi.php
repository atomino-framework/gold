<?php

namespace Atomino\Gold;

use Atomino\Carbon\Entity;
use Atomino\Carbon\Plugins\StoredTree\StoredTreeInterface;
use Atomino\Mercury\Responder\Api\Attributes\Route;

class TreeApi extends ListApi {
	public function __construct(Gold $router) { parent::__construct($router, 0, false); }

	/**
	 * @var Entity|string|StoredTreeInterface
	 */
	protected Entity|string $entity;

	#[Route("POST", '/get')]
	public function getTree() {
		return ["items" => ($this->entity)::tree(null, null, fn($item) => $this->export($item))];
	}
	#[Route("POST", '/set-item')]
	public function setItem(int $id, int $parentId, int $sequence) {
		($this->entity)::treeMove($id, $parentId, $sequence);
	}

	#[Route("POST", "/move-under")]
	public function moveUnder() {
		$id = $this->data->get("id");
		$targetId = $this->data->get("targetId");
		($this->entity)::treeMove($id, $targetId, null);
		return [];
	}

	#[Route("POST", "/move-behind")]
	public function moveBehind() {
		$id = $this->data->get("id");
		$targetId = $this->data->get("targetId");
		$parentId = ($this->entity)::treeManager()->getParent($targetId);
		$sequence = ($this->entity)::treeManager()->getSequence($targetId);
		($this->entity)::treeMove($id, $parentId, $sequence);
		return [];
	}
	#[Route("POST", '/options')]
	public function getOptions(): array {
		return [];
	}
}