<?php namespace Atomino\Gold;

use Atomino\Bundle\Attachment\Attachment;
use Atomino\Bundle\Attachment\AttachmentableInterface;
use Atomino\Bundle\Attachment\FileException;
use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Atomino\Carbon\ValidationError;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use function Atomino\debug;

class GoldApi extends Api {

	/** @var GoldView[] */
	protected array $views = [];
	/** @var GoldSorting[] */
	protected array $sortings = [];
	/** @var Entity */
	protected string $entity;

	protected int $pagesize;
	protected bool $quicksearch;

	public function __construct() { $this->entity = Gold::get(new \ReflectionClass($this))->class; }

	private function getListOptions(): void {
		$reflectionClass = new \ReflectionClass($this);
		$this->pagesize = Gold::get($reflectionClass)->pagesize;
		$this->quicksearch = Gold::get($reflectionClass)->quicksearch;
		$this->addViews();
		$this->addSortings();
	}

	protected final function addView(GoldView $view): void { $this->views[$view->name] = $view; }
	protected final function addSorting(GoldSorting $sorting): void { $this->sortings[$sorting->name] = $sorting; }

	protected function addViews(): void {
		$reflectionClass = new \ReflectionClass($this);
		$methods = $reflectionClass->getMethods();
		foreach ($methods as $method) {
			if (!is_null($view = GoldView::get($method))) {
				$method->setAccessible(true);
				$view->method = fn() => $method->invoke($this, ...func_get_args());
				$this->addView($view);
			}
		}
	}
	protected function addSortings(): void {
		$reflectionClass = new \ReflectionClass($this);
		$methods = $reflectionClass->getMethods();
		foreach ($methods as $method) {
			if (!is_null($sorting = GoldSorting::get($method))) {
				$method->setAccessible(true);
				$sorting->method = fn() => $method->invoke($this, ...func_get_args());
				$this->addSorting($sorting);
			}
		}
	}

	protected function getItem(int|null $id): Entity|null {
		$item = ($this->entity)::pick($id);
		if (is_null($item)) $this->setStatusCode(404);
		return $item;
	}
	protected function listExport(Entity $item): array { return $item->export(); }
	protected function formExport(Entity $item): array { return $item->export(); }
	/** @throws ValidationError */
	protected function updateItem(Entity $item, array $data): int|null { return $item->import($data)->save(); }
	/** @throws ValidationError */
	protected function createItem(Entity $item, array $data): int|null { return $item->import($data)->save(); }
	protected function deleteItem(Entity $item) { $item->delete(); }
	protected function quickSearch(string $search): Filter { return Filter::where("id=$1", $search); }

	protected function selectFilter(string $search): Filter|null { return null; }
	protected function selectMap(Entity $item): string { return $item->id; }

	#region *** ITEM ENDPOINTS ***

	#[Route("POST", '/create')]
	public function create(): int|array {

		$data = $this->data->get("item");
		$item = ($this->entity)::create();

		try {
			$this->createItem($item, $data);
		} catch (ValidationError $e) {
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return $e->getMessages();
		}
		return $item->id;
	}

	#[Route("POST", '/update/:id([0-9]+)')]
	public function update(int $id): null|int|array {
		if (is_null($item = $this->getItem($id))) return null;

		$data = $this->data->get("item");

		try {
			$this->updateItem($item, $data);
		} catch (ValidationError $e) {
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return $e->getMessages();
		}
		return $item->id;
	}

	#[Route("POST", '/delete/:id([0-9]+)')]
	public function delete(int $id) {
		if (is_null($item = $this->getItem($id))) return;
		try{
			$this->deleteItem($item);
		}catch (\Throwable $exception){
			debug("can not delete");
			$this->setStatusCode(Api::VALIDATION_ERROR);
			return [["field"=>"", "message"=>"Can not delete the item"]];
		}
	}

	#[Route("POST", '/get/:id([0-9]+)')]
	public function get(int $id): array|null {
		if (is_null($item = $this->getItem($id))) return null;
		return $this->formExport($item);
	}

	#[Route("POST", '/blank')]
	public function blank(): array {
		return ($this->entity)::create()->export();
	}

	#endregion

	#region *** LIST ENDPOINTS ***

	#[Route("POST", '/list/get')]
	public function listGet() {
		$this->getListOptions();
		$pagesize = $this->data->get('pagesize');
		$page = $this->data->get('page');
		$view = $this->data->get('view');
		$sorting = $this->data->get('sorting');
		$quicksearch = $this->data->get('quicksearch');

		$method = is_null($view) ? null : $this->views[$view]->method;
		/** @var Filter $filter */
		$filter = is_null($method) ? null : $method();

		if ($quicksearch && $this->quicksearch) {
			$quicksearch = $this->quickSearch($quicksearch);
			$filter = is_null($filter) ? $quicksearch : $filter->and($quicksearch);
		}

		$method = null;
		if (!is_null($sorting)) {
			$dir = substr($sorting, 0, 1) === '+';
			$sorting = substr($sorting, 1);
			$method = is_null($sorting) ? null : $this->sortings[$sorting]->method;
		}
		$order = is_null($method) ? [] : $method($dir);

		$items = ($this->entity)::search($filter)->order(...$order)->page($pagesize, $page, $count);
		if (count($items) === 0) {
			$page = max(1, ceil($count / $pagesize));
			$items = ($this->entity)::search($filter)->order(...$order)->page($pagesize, $page, $count);
		}
		return ["items" => array_map(fn(Entity $item) => $this->listExport($item), $items), "count" => $count, "page" => $page];
	}

	#[Route("POST", '/list/options')]
	public function listOptions(): array {
		$this->getListOptions();
		return [
			"quicksearch" => $this->quicksearch,
			"pagesize"    => $this->pagesize,
			"views"       => count($this->views) ? $this->views : null,
			"sortings"    => count($this->sortings) ? $this->sortings : null,
		];
	}

	#endregion

	#region *** COMBOBOX ENDPOINTS ***

	#[Route("POST", "/select/search")]
	public function selectSearch(): array {
		$search = $this->data->get("search");
		$items = ($this->entity)::search($this->selectFilter($search))->collect();
		return array_map(fn($item) => ["key" => $item->id, "value" => $this->selectMap($item)], $items);
	}

	#[Route("POST", "/select/get")]
	public function selectGet(): array {
		$value = $this->data->get("value");
		$items = ($this->entity)::search(Filter::where(($this->entity)::id($value)))->collect();
		return array_map(fn($item) => ["key" => $item->id, "value" => $this->selectMap($item)], $items);
	}

	#endregion

	#region *** ATTACHMENT ENDPOINTS ***

	#[Route(Api::POST, '/attachment/get/:id([0-9]+)')]
	public function getAttachments(int $id): array|bool {
		/**
		 * @var AttachmentableInterface $item
		 */

		if (is_null($item = $this->getItem($id))) return false;

		$collections = [];
		$files = [];

		$attachments = $item->getAttachmentStorage()->attachments;
		$collectionNames = array_keys($item->getAttachmentStorage()->collections);

		foreach ($collectionNames as $collectionName) {
			$collections[$collectionName] = [
				'files'    => $item->getAttachmentStorage()->getCollection($collectionName)->files,
				'maxCount' => $item->getAttachmentStorage()->getCollection($collectionName)->maxCount,
				'maxSize'  => $item->getAttachmentStorage()->getCollection($collectionName)->maxSize,
				'mimetype' => $item->getAttachmentStorage()->getCollection($collectionName)->mimetype,
			];
		}

		foreach ($attachments as $attachment) {
			$file = $attachment->jsonSerialize();
			$file['name'] = $attachment->filename;
			$file['url'] = $attachment->url;
			if ($attachment->isImage) {
				$file['isImage'] = true;
				$file['thumbnail'] = $attachment->image->crop(240, 180)->webp;
			} else {
				$file['isImage'] = false;
			}
			$files[$attachment->filename] = $file;
		}
		return ['collections' => $collections, 'files' => $files,];
	}

	#[Route(Api::POST, '/attachment/save-file-details/:id([0-9]+)')]
	public function saveFileDetails(int $id): bool {
		/**
		 * @var AttachmentableInterface $item
		 * @var Attachment|null $file
		 */

		if (is_null($item = $this->getItem($id))) return false;

		$filename = $this->data->get('filename');
		$data = $this->data->get('data');

		try {
			$file = $item->getAttachmentStorage()->getAttachment($filename);

			$file->storage->begin();
			{
				$file->setFocus($data['focus']);
				$file->setSafezone($data['safezone']);
				$file->setTitle($data['title']);
				$file->setProperties($data['properties']);
			}
			$file->storage->commit();

			if ($filename !== $data['filename']) $file->rename($data['filename']);
			return true;
		} catch (\Throwable $e) {
			$this->setStatusCode(self::VALIDATION_ERROR);
			return false;
		}
	}

	#[Route(Api::POST, '/attachment/remove-file/:id([0-9]+)')]
	public function removeFile(int $id): bool {
		/**
		 * @var AttachmentableInterface $item
		 */
		if (is_null($item = $this->getItem($id))) return false;

		try {
			$filename = $this->data->get('filename');
			$collectionName = $this->data->get('collection');

			$item->getAttachmentStorage()->collections[$collectionName]->remove($filename);
			return true;
		} catch (\Throwable $e) {
			$this->setStatusCode(self::VALIDATION_ERROR);
			return false;
		}
	}


	#[Route(Api::POST, '/attachment/upload/:id([0-9]+)')]
	public function upload(int $id): bool|array {
		/**
		 * @var AttachmentableInterface $item
		 * @var UploadedFile $file
		 */

		if (is_null($item = $this->getItem($id))) return false;

		$collection = $this->post->get('collection');
		$file = $this->files->get('file');

		try {
			$item->getAttachmentStorage()->collections[$collection]->addFile($file);
			return true;
		} catch (\Throwable $e) {
			$this->setStatusCode(self::VALIDATION_ERROR);
			return [['field'=>'attachment', 'message' => $e->getMessage()]];
		}
	}

	#[Route(Api::POST, '/attachment/move-file/:id([0-9]+)')]
	public function moveFile(int $id): bool|array {
		/**
		 * @var AttachmentableInterface $item
		 */

		if (is_null($item = $this->getItem($id))) return false;

		$copy = $this->data->get('copy');
		$filename = $this->data->get('filename');
		$source = $this->data->get('source');
		$target = $this->data->get('target');
		$position = $this->data->get('position') + 1;

		try {
			if ($target !== $source) {
				$item->getAttachmentStorage()->collections[$target]->add($filename);
				if (!$copy) $item->getAttachmentStorage()->collections[$source]->remove($filename);
			}
			$item->getAttachmentStorage()->collections[$target]->get($filename);
			$item->getAttachmentStorage()->collections[$target]->order($filename, $position);
			return true;
		} catch (FileException $e) {
			$this->setStatusCode(self::VALIDATION_ERROR);
			return [['field'=>'attachment', 'message' => $e->getMessage()]];
		}
	}

	#endregion

}
