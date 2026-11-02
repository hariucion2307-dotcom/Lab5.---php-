<?php 
declare(strict_types=1);
session_start();
/**
 * Представляет одну банковскую транзакцию.
 */ 
class Transaction 
{
    private int $id;
    private DateTimeImmutable $date;
    private float $amount;
    private string $description;
    private string $merchant;
    /**
     * Создает транзакцию. 
     * @param int $id Уникальный идентификатор. 
     * @param string $date Дата в формате Y-m-d. 
     * @param float $amount Сумма транзакции. 
     * @param string $description Описание платежа. 
     * @param string $merchant Получатель платежа. 
     */ 
    public function __construct(
        int $id,
        string $date,
        float $amount,
        string $description,
        string $merchant
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID должен быть положительным.');
        } 
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма должна быть положительной.');
        } 
        $this->id = $id;
        $this->date = new DateTimeImmutable($date);
        $this->amount = $amount;
        $this->description = $description;
        $this->merchant = $merchant;
    } 
    /** @return int Возвращает идентификатор транзакции. */
    public function getId(): int
    {
        return $this->id;
    } 
    /** @return DateTimeImmutable Возвращает дату транзакции. */
    public function getDate(): DateTimeImmutable 
    {
        return $this->date;
    } 
    /** @return float Возвращает сумму транзакции. */
    public function getAmount(): float 
    {
        return $this->amount;
    } 
    /** @return string Возвращает описание платежа. */
    public function getDescription(): string 
    {
        return $this->description;
    } 
    /** @return string Возвращает название получателя. */ 
    public function getMerchant(): string 
    {
        return $this->merchant;
    } 
    /**
     * Вычисляет число дней между датой транзакции и сегодняшнем днем. 
     * 
     * @return int Количество полных календарных дней. 
     */ 
    public function getDaysSinceTransaction(): int 
    {
        $today = new DateTimeImmutable('today');
        $transactionDate = $this->date->setTime(0, 0);
        return (int) $transactionDate->diff($today)->format('%a');
    }
} 
/**
 * Определяет операции, которые должно предоставлять хранилище.
 */ 
interface TransactionStorageInterface 
{
    /**
     * Добавляет транзакцию. 
     * 
     * @param Transaction $transaction Транзакция. 
     */
    public function addTransaction(Transaction $transaction): void;
    /**
     * Удаляет транзакцию по идентификатору. 
     * 
     * @param int $id Идентификатор. 
     */ 
    public function removeTransactionById(int $id): void;
    /**
     * Возвращает все транзакции. 
     * 
     * @return array<int, Transaction>
     */ 
    public function getAllTransactions(): array;
    /**
     * Находит транзакцию по идентификатору. 
     * 
     * @param int $id Идентификатор. 
     * @return Transaction|null Найденная транзакция или null. 
     */ 
    public function findById(int $id): ?Transaction;
} 
/**
 * Хранит коллекцию транзакций и выполняет базовые операции доступа. 
 */ 
class TransactionRepository implements TransactionStorageInterface 
{
    /** @var array<int, Transaction> */ 
    private array $transactions = [];
    /**
     * Добавляет транзакцию, если ее ID еще не используется. 
     * 
     * @param Transaction $transaction Транзакция. 
     */ 
    public function addTransaction(Transaction $transaction): void 
    {
        if ($this->findById($transaction->getId()) !== null) {
            throw new InvalidArgumentException('Транзакция с таким ID уже существует.');
        }
        $this->transactions[] = $transaction;
    }
    /**
     * Удаляет транзакцию по ID. 
     * 
     * @param int $id Идентификатор. 
     */ 
    public function removeTransactionById(int $id): void 
    {
        foreach ($this->transactions as $key => $transaction) {
            if ($transaction->getId() === $id) {
                unset($this->transactions[$key]);
                $this->transactions = array_values($this->transactions);
                return;
            }
        }
    }
    /**
     * Возвращает все транзакции. 
     * 
     * @return array<int, Transaction>
     */
    public function getAllTransactions(): array 
    {
        return $this->transactions;
    } 
    /**
     * Ищет транзакцию по ID. 
     * 
     * @param int $id Идентификатор. 
     * @return Transaction|null 
     */ 
    public function findById(int $id): ?Transaction 
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->getId() === $id) {
                return $transaction;
            } 
        } 
        return null;
    } 
} 
/**
 * Выполняет вычисления и сортировку коллекции транзакций. 
 */ 
class TransactionManager 
{
    /**
     * Принимает хранилище через интерфейс. 
     * 
     * @param TransactionStorageInterface $repository Хранилище. 
     */ 
    public function __construct(private TransactionStorageInterface $repository) {
    }
    /**
     * Вычисляет общую сумму транзакций. 
     * 
     * @return float Oбщая сумма. 
     */ 
    public function calculateTotalAmount(): float 
    {
        $total = 0.0;
        foreach ($this->repository->getAllTransactions() as $transaction) { 
            $total += $transaction->getAmount();
        } 
        return $total;
    } 
    /**
     * Вычисляет сумму транзакций за период включительно. 
     * 
     * @param string $startDate Начальная дата в формате Y-m-d. 
     * @param string $endDate Конечная дата в формате Y-m-d. 
     * @return float Сумма за период. 
     */ 
    public function calculateTotalAmountByDateRange(string $startDate, string $endDate): float {
        $start = new DateTimeImmutable($startDate); 
        $end = (new DateTimeImmutable($endDate))->setTime(23, 59, 59);
        $total = 0.0;
        foreach ($this->repository->getAllTransactions() as $transaction) {
            $date = $transaction->getDate();
            if ($date >= $start && $date <= $end) {
                $total += $transaction->getAmount(); 
            }
        } 
        return $total;
    } 
    /**
     * Подсчитывает транзакции указанного получателя. 
     * 
     * @param string $merchant Название получателя. 
     * @return int Количество транзакций. 
     */ 
    public function countTransactionsByMerchant(string $merchant): int 
    {
        $count = 0;
        foreach ($this->repository->getAllTransactions() as $transaction) {
            if (strcasecmp($transaction->getMerchant(), $merchant) === 0) {
                $count++;
            }
        } 
        return $count;
    } 
    /**
     * Сортирует транзакции по дате от старых к новым. 
     * 
     * @return array<int, Transaction>
     */ 
    public function sortTransactionsByDate(): array 
    {
        $transactions = $this->repository->getAllTransactions();
        usort(
            $transactions, fn(Transaction $a, Transaction $b): int => $a->getDate() <=> $b->getDate() 
        );
        return $transactions;
    } 
    /**
     * Сортирует транзакции по сумме по убыванию. 
     * 
     * @return array<int, Transaction>
     */ 
    public function sortTransactionsByAmountDesc(): array
    {
        $transactions = $this->repository->getAllTransactions();
        usort(
            $transactions, fn(Transaction $a, Transaction $b): int => $b->getAmount() <=> $a->getAmount() 
        ); 
         return $transactions;
    }
} 
/**
 * Формирует HTML-таблицу транзакций. 
 */
final class TransactionTableRenderer 
{
    /**
     * Экранирует текст для безопасного вывода в HTML. 
     * 
     * @param string $value Исходный текст. 
     * @return string Экранированный текст. 
     */ 
    private function escape(string $value): string 
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } 
    /**
     * Определяет категорию получателя. 
     * 
     * @param string $merchant Название получателя. 
     * @return string Категория. 
     */ 
    private function getMerchantCategory(string $merchant): string 
    {
        $categories = [
            'Supermarket' => 'Продукты', 
            'Netflix' => 'Развлечения', 
            'Amazon' => 'Интернет-магазин', 
            'Pharmacy' => 'Здоровье', 
            'Electricity' => 'Коммунальные услуги', 
            'Restaurant' => 'Ресторан', 
            'Gas Station' => 'Транспорт', 
            'Bookstore' => 'Книги', 
            'Gym' => 'Спорт', 
            'Internet Provider' => 'Связь', 
            'Supermarket Central' => 'Продукты', 
        ]; 
        return $categories[$merchant] ?? 'Другое'; 
    }
    /**
     * Создает HTML-таблицу. 
     * 
     * @param array<int, Transaction> $transactions Список транзакций. 
     * @return string HTML-код таблицы. 
     */ 
    public function render(array $transactions): string 
    {
        $html = '<div class="table-wrap"><table>';
        $html .= '<thead><tr>';
        $html .= '<th>ID</th><th>Дата</th><th>Сумма</th>'; 
        $html .= '<th>Описание</th><th>Получатель</th>';
        $html .= '<th>Категория</th><th>Дней прошло</th><th>Действие</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($transactions as $transaction) {
            $html .= '<tr>';
            $html .= '<td>' . $transaction->getId() . '</td>';
            $html .= '<td>' . $transaction->getDate()->format('Y-m-d') . '</td>';
            $html .= '<td>' . number_format($transaction->getAmount(), 2, '.', ' ') . '</td>';
            $html .= '<td>' . $this->escape($transaction->getDescription()) . '</td>';
            $html .= '<td>' . $this->escape($transaction->getMerchant()) . '</td>';
            $html .= '<td>' . $this->escape($this->getMerchantCategory($transaction->getMerchant())) . '</td>';
            $html .= '<td>' . $transaction->getDaysSinceTransaction() . '</td>';
            $html .= '<td>
                <form method="post">
                    <input type="hidden" name="delete_id"
                        value="' . $transaction->getId() . '">
                    <button type="submit" class="delete-button">Удалить</button>
                </form>
            </td>';
            $html .= '</tr>';
        }
        if ($transactions === []) {
            $html .= '<tr><td colspan="8">Транзакции не найдены.</td></tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }
} 
/**
 * Экранирует текст перед выводом в HTML. 
 * 
 * @param string $value Исходный текст. 
 * @return string Безопасный HTML-текст. 
 */ 
function h(string $value): string 
{
   return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
} 
/**
 * Проверяет дату в формате Y-m-d. 
 * 
 * @param string $value Проверяемая дата. 
 * @return bool Результат проверки. 
 */ 
function isValidDate(string $value): bool 
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && ($errors ===  false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('Y-m-d') === $value;
} 
/**
 * Создает десять начальных транзакций. 
 * 
 * @return array<int, Transaction> Начальные данные. 
 */ 
function createInitialTransactions(): array 
{
    return [
        new Transaction(1, '2026-10-01', 120.50, 'Покупка продуктов', 'Supermarket'),
        new Transaction(2, '2026-10-02', 45.00, 'Подписка на фильмы', 'Netflix'),
        new Transaction(3, '2026-10-03', 250.00, 'Покупка товаров', 'Amazon'),
        new Transaction(4, '2026-10-04', 75.25, 'Покупка лекарств', 'Pharmacy'),
        new Transaction(5, '2026-10-05', 180.00, 'Оплата электричества', 'Electricity'),
        new Transaction(6, '2026-10-06', 32.50, 'Обед', 'Restaurant'),
        new Transaction(7, '2026-10-07', 60.00, 'Заправка автомобиля', 'Gas Station'),
        new Transaction(8, '2026-10-08', 28.90, 'Покупка книги', 'Bookstore'),
        new Transaction(9, '2026-10-09', 40.00, 'Абонемент в зал', 'Gym'),
         new Transaction(10, '2026-10-10', 35.00, 'Оплата интернета', 'Internet Provider'),
    ];
} 
/** Инициализация данных при первом посещении страницы. */
if (!isset($_SESSION['transactions'])) {
    $_SESSION['transactions'] = createInitialTransactions(); 
} 
/** Создаем репозиторий и заполняем его сохраненными транзакциями. */
$repository = new TransactionRepository();
foreach ($_SESSION['transactions'] as $transaction) {
    $repository->addTransaction($transaction);
} 
/** Обрабатываем удаление транзакции. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null) {
        $repository->removeTransactionById($id);
        $_SESSION['transactions'] = $repository->getAllTransactions();
    } 
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}  
/** Обрабатываем добавление новой транзакции. */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $date = trim((string) ($_POST['date'] ?? ''));
    $amountText = trim((string) ($_POST['amount'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $merchant = trim((string) ($_POST['merchant'] ?? ''));
    $amount = filter_var($amountText, FILTER_VALIDATE_FLOAT);
    if (isValidDate($date) && $amount !== false && $amount > 0 && $description !== '' && $merchant !== '') {
        $maxId = 0;
        foreach ($repository->getAllTransactions() as $transaction) {
            $maxId = max($maxId, $transaction->getId());
        } 
        $repository->addTransaction(new Transaction($maxId + 1, $date, (float) $amount, $description, $merchant));
        $_SESSION['transactions'] = $repository->getAllTransactions();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } 
    $error = 'Проверьте дату, сумму, описание и получателя.';
} 
/** Создаем менеджер бизнес-логики. */
$manager = new TransactionManager($repository);
/** Получаем параметры поиска и сортировки. */
$search = trim((string) ($_GET['search'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'default');
/** Получаем отсортированный список через TransactionManager. */
if ($sort === 'date') {
    $displayTransactions = $manager->sortTransactionsByDate();
} elseif ($sort === 'amount') {
    $displayTransactions = $manager->sortTransactionsByAmountDesc();
} else {
    $displayTransactions = $repository->getAllTransactions(); 
} 
/** Фильтруем транзакции по ID, описанию или получателю. */
if ($search !== '') {
    $displayTransactions = array_values(array_filter(
        $displayTransactions,
        fn(Transaction $transaction): bool => 
         (string) $transaction->getId() === $search
         || stripos($transaction->getDescription(), $search) !== false
         || stripos($transaction->getMerchant(), $search) !== false   
    ));
} 
/** Вычисляем показатели для страницы. */ 
$totalAmount = $manager->calculateTotalAmount();
$totalCount = count($repository->getAllTransactions());
$supermarketCount = $manager->countTransactionsByMerchant('Supermarket');
?> 
<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Банковские транзакции</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                padding: 30px;
                background: #f3f5f9;
                color: #202938; 
                font-family: Arial, sans-serif;
            } 
            .container { max-width: 1400px; margin: auto; } 
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
                gap: 16px;
            } 
            .stat, .panel {
                background: white;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 3px 12px #182b4510;
            } 
            .stat p {color: #687386; }
            .stat strong { font-size: 24px; } 
            .form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 14px;
                align-items: end;
            } 
            label {
                display: block;
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 7px;
            } 
            input, select, button {
                width: 100%;
                padding: 11px;
                border: 1px solid #d8deea;
                border-radius: 7px;
                font-size: 14px;
            } 
            button {
                background: #245ce2;
                color: white;
                border: none;
                cursor: pointer;
            } 
            .delete-button { background: #dc3545; }
            .table-wrap { overflow-x: auto; }
            table {
                width: 100%;
                border-collapse: collapse;
            } 
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e9edf3;
            } 
            th { background: #edf2fb; }
            td form { margin: 0; } 
            .search-row {
                display: grid;
                grid-template-columns: 2fr 1fr auto;
                gap: 12px;
                align-items: end;
            } 
            .error { color: #b42318; }
            .reset-link {
                display: inline-block;
                margin-top: 10px;
            } 
            @media (max-width: 650px) {
                body { padding: 14px; } 
                .search-row { grid-template-columns: 1fr; }
            } 
            </style>
            </head>
            <body>
                <div class="container">
                    <h1>Банковские транзакции</h1>
                    <p>Приложение для управления банковскими платежами</p>
                    <div class="stats">
                        <div class="stat">
                            <p>Всего транзакций</p>
                            <strong><?= $totalCount ?></strong>
</div> 
<div class="stat">
    <p>Общая сумма</p>
    <strong><?= number_format($totalAmount, 2, '.', ' ') ?></strong>
</div>
<div class="stat">
    <p>Платежи в Supermarket</p>
    <strong><?= $supermarketCount ?></strong>
</div> 
<div class="stat">
    <p>Результаты поиска</p>
    <strong><?= count($displayTransactions) ?></strong>
</div>
</div> 
<section class="panel">
    <h2>Добавить транзакцию</h2>
    <?php if ($error !== ''): ?>
        <p class="error"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="add_transaction" value="1">
            <div>
                <label for="date">Дата</label>
                <input type="date" id="date" name="date" value="<?= h((string) ($_POST['date'] ?? date('Y-m-d'))) ?>" required>
    </div>
    <div>
        <label for="amount">Сумма</label>
        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>
    </div>
    <div>
        <label for="description">Описание</label>
        <input type="text" id="description" name="description" maxlength="200" required>
    </div>
    <div>
        <label for="merchant">Получатель</label>
        <input type="text" id="merchant" name="merchant" maxlength="100" required>
    </div>
    <div>
        <button type="submit">Добавить</button>
    </div>
    </form>
    </section>
    <section class="panel">
        <h2>Поиск и сортировка</h2>
        <form method="get" class="search-row">
            <div>
                <label for="search">Поиск по ID, описанию или получателю</label>
                <input type="text" id="search" name="search" value="<?= h($search) ?>">
    </div>
    <div>
                <label for="sort">Сортировка</label>
                <select id="sort" name="sort">
                    <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>
                        По умолчанию
                    </option>
                    <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>
                        По дате: от старых к новым 
                    </option>
                    <option value="amount" <?= $sort === 'amount' ? 'selected' : '' ?>>
                        По сумме: по убыванию 
                    </option> 
    </select>
    </div> 
    <div>
        <button type="submit">Применить</button>
        <a class="reset-link" href="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>">Сбросить</a>
    </div>
    </form>
    </section>
    <section class="panel">
        <h2>Список транзакций</h2>
        <?php 
        $renderer = new TransactionTableRenderer();
        echo $renderer->render($displayTransactions);
        ?>
        </section>
        <section class="panel">
        <h2>Расчет суммы за период</h2>
        <form method="get" class="form-grid">
            <input type="hidden" name="search" value="<?= h($search) ?>">
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <div> 
                <label for="start_date">Начало периода</label>
                <input type="date" id="start_date" name="start_date" value="<?= h((string) ($_GET['start_date'] ?? '2026-10-01')) ?>" required>
    </div>
    <div>
        <label for="end_date">Конец периода</label>
                <input type="date" id="end_date" name="end_date" value="<?= h((string) ($_GET['end_date'] ?? '2026-10-31')) ?>" required>
    </div>
    <div>
        <button type="submit">Рассчитать</button>
    </div>
    </form>
    <?php 
    if (isset($_GET['start_date'], $_GET['end_date'])) {
        $startDate = (string) $_GET['start_date'];
        $endDate = (string) $_GET['end_date']; 
        if (
            isValidDate($startDate) && isValidDate($endDate) && $startDate <= $endDate) { 
                $periodTotal = $manager->calculateTotalAmountByDateRange($startDate, $endDate);
                echo '<p>Сумма с ' . h($startDate) . ' по ' . h($endDate) . ': <strong>' . number_format($periodTotal, 2, '.', ' ') . '</strong></p>';
            } else {
                echo '<p class="error">Проверьте даты периода.</p>';
            } 
    } 
    ?>
    </section>
    </div>
    </body>
    </html>




                        

