<?php
declare(strict_types=1);

class ScraperException extends Exception
{
    /** Ошибка связана с соединением (недоступен трекер, таймаут и т.п.) */
    private bool $connectionerror;

    /**
     * @param string $message          Сообщение об ошибке
     * @param int    $code             Код ошибки
     * @param bool   $connectionerror  Является ли ошибка ошибкой соединения
     */
    public function __construct(string $message, int $code = 0, bool $connectionerror = false)
    {
        $this->connectionerror = $connectionerror;
        parent::__construct($message, $code);
    }

    /** Возвращает true, если ошибка относится к соединению */
    public function isConnectionError(): bool
    {
        return $this->connectionerror;
    }
}

abstract class tscraper
{
    /** Таймаут соединения (сек.) */
    protected int $timeout;

    /**
     * @param int $timeout Таймаут (сек.)
     */
    public function __construct(int $timeout = 2)
    {
        $this->timeout = max(1, $timeout);
    }
}
