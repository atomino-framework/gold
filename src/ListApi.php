<?php namespace Atomino\Gold;

use Atomino\Carbon\Database\Finder\Filter;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Attributes\Route;

class ListApi extends AbstractApi {

	protected Entity|string $entity;
	/** @var array{quicksearch:boolean, pagesize:int, views: array<GoldView>|false, sortings: array<GoldSorting>|false} */
	protected array $options;

	public function __construct(Gold $router, int $pagesize = 50, bool $quickSearchAvailable = false) {
		parent::__construct($router);
		$views = [];
		foreach ($this->views() as $item) $views[$item->name] = $item;
		$sortings = [];
		foreach ($this->sortings() as $item) $sortings[$item->name] = $item;
		$this->options = [
			"quicksearch" => $quickSearchAvailable,
			"pagesize"    => $pagesize,
			"views"       => count($views) ? $views : false,
			"sortings"    => count($sortings) ? $sortings : false,
		];
	}

	/** @return GoldView[] */
	public function views(): array { return []; }

	/** @return GoldSorting[] */
	public function sortings(): array { return []; }

	public function quickSearchFilter(string $search): Filter { return Filter::where("id=$1", $search); }
	public function searchFilter(array $filter): Filter|null { return null; }
	public function baseFilter(): Filter|null { return null; }
	public function export(Entity $item): array { return $item->export(); }


	#[Route("POST", '/get')]
	public final function POST_get() {

		$arg_pagesize = $this->data->get('pagesize');
		$arg_page = $this->data->get('page');
		$arg_view = $this->data->get('view');
		$arg_sorting = $this->data->get('sorting');
		$arg_quicksearch = $this->data->get('quicksearch');
		$arg_filter = $this->data->get("filter");

		$filter = Filter::where();
		if (!is_null($arg_view) && $this->options["views"] !== false) $filter->and(($this->options["views"][$arg_view]->method)());
		if ($arg_quicksearch && $this->options["quicksearch"]) $filter->and($this->quickSearchFilter($arg_quicksearch));
		if (is_array($arg_filter) && count($arg_filter)) $filter->and($this->searchFilter($arg_filter));
		if (!is_null($baseFilter = $this->baseFilter())) $filter->and($baseFilter);

		$order = (function ($sorting) {
			if (!is_null($sorting)) {
				$dir = substr($sorting, 0, 1) === '+';
				$sorting = substr($sorting, 1);
				return ($this->options["sortings"][$sorting]->method)($dir);
			}
			return [];
		})($arg_sorting);

		$items = ($this->entity)::search($filter)->order(...$order)->page($arg_pagesize, $arg_page, $count);

		return [
			"items" => array_map(fn(Entity $item) => $this->export($item), $items),
			"count" => $count,
			"page"  => $arg_page,
		];
	}

	#[Route("POST", '/options')]
	public final function POST_options(): array {
		return $this->options;
	}

}
