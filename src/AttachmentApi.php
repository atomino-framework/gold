<?php namespace Atomino\Gold;

use Atomino\Bundle\Attachment\AttachmentableInterface;
use Atomino\Bundle\Attachment\FileException;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentApi extends AbstractApi {

	private function get(int|null $id): AttachmentableInterface|null {
		$item = ($this->entity)::pick($id);
		if (is_null($item)) $this->setStatusCode(404);
		return $item;
	}

	#[Route(Api::POST, '/get/:id([0-9]+)')]
	public function POST_get(int $id): array|bool {
		if (is_null($item = $this->get($id))) return false;

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

	#[Route(Api::POST, '/save-file-details/:id([0-9]+)')]
	public function POST_saveFileDetails(int $id): bool {
		if (is_null($item = $this->get($id))) return false;

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

	#[Route(Api::POST, '/remove-file/:id([0-9]+)')]
	public function POST_removeFile(int $id): bool {
		if (is_null($item = $this->get($id))) return false;

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

	#[Route(Api::POST, '/upload/:id([0-9]+)')]
	public function POST_upload(int $id): bool|array {
		if (is_null($item = $this->get($id))) return false;

		$collection = $this->post->get('collection');
		$file = $this->files->get('file');
		$file = (fn($file): UploadedFile => $file)($file);

		try {
			$item->getAttachmentStorage()->collections[$collection]->addFile($file);
			return true;
		} catch (\Throwable $e) {
			$this->setStatusCode(self::VALIDATION_ERROR);
			return [['field' => 'attachment', 'message' => $e->getMessage()]];
		}
	}

	#[Route(Api::POST, '/move-file/:id([0-9]+)')]
	public function POST_moveFile(int $id): bool|array {
		if (is_null($item = $this->get($id))) return false;

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
			return [['field' => 'attachment', 'message' => $e->getMessage()]];
		}
	}

}
