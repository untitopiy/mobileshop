<?php
session_start();
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../inc/db.php';

// Проверка идентификатора аксессуара
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container'><h2>Ошибка: Неверный идентификатор аксессуара.</h2></div>";
    exit;
}
$accessory_id = (int)$_GET['id'];

// Обработка добавления поддерживаемого устройства
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_smartphone'])) {
    $smartphone_id = (int)$_POST['smartphone_id'];
    // Проверяем, нет ли уже такой записи
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM accessory_supported_smartphones WHERE accessory_id = ? AND smartphone_id = ?");
    $stmt->bind_param("ii", $accessory_id, $smartphone_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($res['cnt'] == 0) {
        $stmt = $db->prepare("INSERT INTO accessory_supported_smartphones (accessory_id, smartphone_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $accessory_id, $smartphone_id);
        $stmt->execute();
        $stmt->close();
        header("Location: accessory_supported.php?id=" . $accessory_id);
        exit;
    } else {
        echo "<div class='container'><div class='alert alert-info'>Это устройство уже добавлено.</div></div>";
    }
}

// Обработка удаления поддерживаемого устройства (через GET-параметр "delete")
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $smartphone_id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM accessory_supported_smartphones WHERE accessory_id = ? AND smartphone_id = ?");
    $stmt->bind_param("ii", $accessory_id, $smartphone_id);
    $stmt->execute();
    $stmt->close();
    header("Location: accessory_supported.php?id=" . $accessory_id);
    exit;
}

// Получаем данные аксессуара
$stmt = $db->prepare("SELECT id, name FROM accessories WHERE id = ?");
$stmt->bind_param("i", $accessory_id);
$stmt->execute();
$accessory = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$accessory) {
    echo "<div class='container'><h2>Аксессуар не найден.</h2></div>";
    exit;
}

// Получаем список поддерживаемых устройств для данного аксессуара
$stmt = $db->prepare("SELECT s.id, s.name, s.brand, s.model 
                      FROM accessory_supported_smartphones AS ass
                      JOIN smartphones AS s ON ass.smartphone_id = s.id
                      WHERE ass.accessory_id = ?");
$stmt->bind_param("i", $accessory_id);
$stmt->execute();
$supported = $stmt->get_result();
$stmt->close();

// Получаем список всех смартфонов для выбора (выводим все устройства)
$allSmartphones = $db->query("SELECT id, name, brand, model FROM smartphones");
?>

<?php require_once __DIR__ . '/../inc/header.php'; ?>
<div class="container my-5">
    <h1>Поддерживаемые устройства для аксессуара: <?= htmlspecialchars($accessory['name']) ?></h1>
    
    <!-- Форма добавления устройства -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="POST" class="d-flex align-items-center flex-wrap">
                <label for="smartphone_id" class="form-label me-2 mb-0">Добавить устройство:</label>
                <select name="smartphone_id" id="smartphone_id" class="form-select form-select-sm me-2" style="width: auto;">
                    <?php while ($row = $allSmartphones->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>">
                            <?= htmlspecialchars($row['brand'] . ' ' . $row['name'] . ' ' . $row['model']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="add_smartphone" class="btn btn-sm btn-bottom mt-3">Добавить</button>
            </form>
        </div>
    </div>
    
    <!-- Таблица поддерживаемых устройств -->
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>ID устройства</th>
                <th>Название</th>
                <th>Бренд</th>
                <th>Модель</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($supported->num_rows > 0): ?>
                <?php while ($s = $supported->fetch_assoc()): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['brand']) ?></td>
                        <td><?= htmlspecialchars($s['model']) ?></td>
                        <td>
                            <a href="accessory_supported.php?id=<?= $accessory_id ?>&delete=<?= $s['id'] ?>" class="btn btn-sm btn-bottom" onclick="return confirm('Удалить это устройство?');">Удалить</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">Нет поддерживаемых устройств.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Нижняя панель навигации -->
    <div class="d-flex justify-content-start mt-4">
        <a href="manage_products.php" class="btn btn-bottom me-2">Назад к товарам</a>
        <a href="index.php" class="btn btn-bottom">Админка</a>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
