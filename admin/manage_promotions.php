<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

// доступ только для админа
if (!isset($_SESSION['id'])) {
    header("Location: http://{$_SERVER['HTTP_HOST']}/mobileshop/pages/auth.php");
    exit;
}
$user_id = $_SESSION['id'];
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();
if (!$is_admin) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$warning = '';    // Для вывода предупреждения
$promo   = null;  // Данные редактируемой акции

// CREATE или UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $pt     = $_POST['product_type'];
    $pid    = (int)$_POST['product_id'];
    $disc   = max(1, min(100, (int)$_POST['discount_percent']));
    $sd     = $_POST['start_date'];
    $ed     = isset($_POST['indefinite']) ? null : $_POST['end_date'];
    $ia     = isset($_POST['is_active']) ? 1 : 0;

    // Если создаём, то проверяем конфликт
    if ($action === 'create') {
        $chk = $db->prepare("
            SELECT * FROM promotions
             WHERE product_type = ? AND product_id = ?
             LIMIT 1
        ");
        $chk->bind_param("si", $pt, $pid);
        $chk->execute();
        $res = $chk->get_result();
        if ($row = $res->fetch_assoc()) {
            // Конфликт: переводим форму в режим редактирования существующей акции
            $promo = $row;
            // подставляем новые поля
            $promo['discount_percent'] = $disc;
            $promo['start_date']       = $sd;
            $promo['end_date']         = $ed;
            $promo['is_active']        = $ia;
            $warning = "Для этого товара уже существует акция (ID {$row['id']}). Вы можете её обновить.";
            // меняем action на update
            $_POST['action'] = 'update';
            $_POST['id']     = $row['id'];
            $action = 'update';
        }
        $chk->close();
    }

    // если после проверки action всё ещё create — вставляем
    if ($action === 'create') {
        $ins = $db->prepare("
            INSERT INTO promotions
                (product_type,product_id,discount_percent,start_date,end_date,is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("siissi", $pt, $pid, $disc, $sd, $ed, $ia);
        $ins->execute();
        $ins->close();
        header("Location: manage_promotions.php");
        exit;
    }

    // UPDATE
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $upd = $db->prepare("
            UPDATE promotions SET
                product_type=?, product_id=?, discount_percent=?,
                start_date=?, end_date=?, is_active=?
            WHERE id=?
        ");
        $upd->bind_param("siissii", $pt, $pid, $disc, $sd, $ed, $ia, $id);
        $upd->execute();
        $upd->close();
        header("Location: manage_promotions.php");
        exit;
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM promotions WHERE id=$id");
    header("Location: manage_promotions.php");
    exit;
}

// ВКЛ/ВЫКЛ
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->query("UPDATE promotions SET is_active = 1 - is_active WHERE id=$id");
    header("Location: manage_promotions.php");
    exit;
}

// Редактирование — загрузка одной записи
if (!empty($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $db->query("SELECT * FROM promotions WHERE id=$id");
    $promo = $res->fetch_assoc();
}

// списки товаров
$sm = $db->query("SELECT id, brand, name, price FROM smartphones");
$ac = $db->query("SELECT id, brand, name, price FROM accessories");
// все акции + price
$all = $db->query("
    SELECT p.*,
           COALESCE(s.brand, a.brand) AS brand,
           COALESCE(s.name, a.name)   AS name,
           COALESCE(s.price, a.price) AS original_price
      FROM promotions p
 LEFT JOIN smartphones s ON p.product_type='smartphone' AND p.product_id=s.id
 LEFT JOIN accessories a ON p.product_type='accessory'  AND p.product_id=a.id
     ORDER BY p.start_date DESC
");

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container my-5">
  <h1>Управление акциями</h1>
  <?php if ($warning): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($warning) ?></div>
  <?php endif; ?>

  <table class="table table-bordered table-striped mt-4">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Товар</th>
        <th>Старая цена</th>
        <th>Новая цена</th>
        <th>Скидка</th>
        <th>Период</th>
        <th>Активно</th>
        <th>Действия</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($r = $all->fetch_assoc()): ?>
        <?php
          $orig = (float)$r['original_price'];
          $new  = $orig * (100 - $r['discount_percent']) / 100;
        ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['brand'].' '.$r['name']) ?></td>
          <td><?= number_format($orig, 2, ',', ' ') ?> руб.</td>
          <td><?= number_format($new, 2, ',', ' ') ?> руб.</td>
          <td><?= $r['discount_percent'] ?>%</td>
          <td>
            <?= $r['start_date'] ?>
            — 
            <?= $r['end_date'] ?? '<em>бессрочно</em>' ?>
          </td>
          <td><?= $r['is_active'] ? 'Да' : 'Нет' ?></td>
          <td>
            <div class="btn-group" role="group">
              <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm action-btn">Изменить</a>
              <a href="?toggle=<?= $r['id'] ?>" class="btn btn-sm action-btn">
                <?= $r['is_active'] ? 'Выключить' : 'Включить' ?>
              </a>
              <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm action-btn" onclick="return confirm('Удалить акцию?')">
                Удалить
              </a>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <hr class="my-4">

  <h2><?= $promo ? 'Редактировать' : 'Добавить' ?> акцию</h2>
  <form method="POST" class="mt-3">
    <input type="hidden" name="action" value="<?= $promo ? 'update' : 'create' ?>">
    <?php if ($promo): ?>
      <input type="hidden" name="id" value="<?= $promo['id'] ?>">
    <?php endif; ?>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Тип товара</label>
        <select name="product_type" id="ptype" class="form-select">
          <option value="smartphone" <?= ($promo['product_type'] ?? '') === 'smartphone' ? 'selected' : '' ?>>Смартфон</option>
          <option value="accessory"  <?= ($promo['product_type'] ?? '') === 'accessory'  ? 'selected' : '' ?>>Аксессуар</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Товар</label>
        <select name="product_id" id="pid" class="form-select" required></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Скидка (%)</label>
        <input
          type="number"
          name="discount_percent"
          class="form-control"
          min="1" max="100"
          value="<?= $promo['discount_percent'] ?? '' ?>"
          required
        >
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">С (начало)</label>
        <input
          type="date"
          name="start_date"
          class="form-control"
          value="<?= $promo['start_date'] ?? date('Y-m-d') ?>"
          required
        >
      </div>
      <div class="col-md-4">
        <label class="form-label">По (окончание)</label>
        <input
          type="date"
          name="end_date"
          id="end_date"
          class="form-control"
          value="<?= $promo['end_date'] ?? '' ?>"
          <?= empty($promo['end_date']) ? 'disabled' : '' ?>
        >
      </div>
      <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
          <input
            type="checkbox"
            name="indefinite"
            id="indefinite"
            class="form-check-input"
            <?= empty($promo['end_date']) ? 'checked' : '' ?>
          >
          <label for="indefinite" class="form-check-label">Бессрочно</label>
        </div>
      </div>
    </div>

    <div class="form-check mb-4">
      <input
        type="checkbox"
        name="is_active"
        id="ia"
        class="form-check-input"
        <?= ($promo['is_active'] ?? 1) ? 'checked' : '' ?>
      >
      <label for="ia" class="form-check-label">Активна</label>
    </div>

    <button class="btn btn-primary"><?= $promo ? 'Сохранить' : 'Добавить' ?></button>
  </form>
</div>

<script>
// данные для селекторов
const smartphones = <?= json_encode($sm->fetch_all(MYSQLI_ASSOC)) ?>;
const accessories  = <?= json_encode($ac->fetch_all(MYSQLI_ASSOC)) ?>;
const ptype        = document.getElementById('ptype');
const pid          = document.getElementById('pid');
const indefinite   = document.getElementById('indefinite');
const endDateEl    = document.getElementById('end_date');
const selectedPid  = <?= json_encode($promo['product_id'] ?? null) ?>;

function loadProducts() {
  const list = ptype.value === 'smartphone' ? smartphones : accessories;
  pid.innerHTML = list.map(o => `
    <option value="${o.id}" ${o.id == selectedPid ? 'selected' : ''}>
      ${o.brand} ${o.name}
    </option>
  `).join('');
}

ptype.addEventListener('change', loadProducts);
loadProducts();

indefinite.addEventListener('change', () => {
  endDateEl.disabled = indefinite.checked;
  if (indefinite.checked) endDateEl.value = '';
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
