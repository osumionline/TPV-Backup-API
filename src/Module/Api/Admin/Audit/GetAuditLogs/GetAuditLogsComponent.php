<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Audit\GetAuditLogs;

use Osumi\OsumiFramework\App\Component\Model\AuditLogList\AuditLogListComponent;
use Osumi\OsumiFramework\App\DTO\GetAuditLogsDTO;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\Core\OComponent;

class GetAuditLogsComponent extends OComponent {
  private const MAX_PAGE_SIZE = 100;

  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public string $message = '';

  public int $page = 1;
  public int $page_size = 25;
  public int $total = 0;
  public int $total_pages = 0;

  public ?AuditLogListComponent $list = null;

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->audit_log_service = inject(
      AuditLogService::class
    );

    $this->list = new AuditLogListComponent();
  }

  /**
   * Gets one paginated page of audit events.
   *
   * @param GetAuditLogsDTO $dto Pagination data.
   *
   * @return void
   */
  public function run(
    GetAuditLogsDTO $dto
  ): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->page) ||
      is_null($dto->pageSize) ||
      $dto->page < 1 ||
      $dto->pageSize < 1 ||
      $dto->pageSize > self::MAX_PAGE_SIZE
    ) {
      $this->message = 'Invalid pagination values.';
      $core->setHttpStatus(400);
      return;
    }

    $this->page = $dto->page;
    $this->page_size = $dto->pageSize;

    $this->total = $this->audit_log_service->countAll();

    $this->total_pages = $this->total === 0
      ? 0
      : (int) ceil(
        $this->total /
        $this->page_size
      );

    $this->list->list = $this->audit_log_service->getPage(
      $this->page,
      $this->page_size
    );

    $this->status = 'ok';
  }
}
