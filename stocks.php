<?php
session_start();
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';

$pp = 12;
$page_s = isset($_GET['page_s']) ? max(1,(int)$_GET['page_s']) : 1;
$page_a = isset($_GET['page_a']) ? max(1,(int)$_GET['page_a']) : 1;
$off_s  = ($page_s-1)*$pp;
$off_a  = ($page_a-1)*$pp;

$cnt_s  = $db->query("
    SELECT COUNT(*) c
      FROM promotions p
      JOIN smartphones s ON p.product_type='smartphone' AND p.product_id=s.id
     WHERE p.is_active=1 AND p.start_date<=CURDATE() AND (p.end_date>=CURDATE() OR p.end_date IS NULL)
")->fetch_assoc()['c'];
$cnt_a  = $db->query("
    SELECT COUNT(*) c
      FROM promotions p
      JOIN accessories a ON p.product_type='accessory' AND p.product_id=a.id
     WHERE p.is_active=1 AND p.start_date<=CURDATE() AND (p.end_date>=CURDATE() OR p.end_date IS NULL)
")->fetch_assoc()['c'];
$tp_s = ceil($cnt_s/$pp);
$tp_a = ceil($cnt_a/$pp);

$phones = $db->query("
    SELECT s.id,s.brand,s.name,s.model,s.price op,p.discount_percent,
           (SELECT image_url FROM smartphone_images WHERE smartphone_id=s.id LIMIT 1) img,s.stock
      FROM promotions p
      JOIN smartphones s ON p.product_type='smartphone' AND p.product_id=s.id
     WHERE p.is_active=1 AND p.start_date<=CURDATE() AND (p.end_date>=CURDATE() OR p.end_date IS NULL)
     ORDER BY p.start_date DESC
     LIMIT $pp OFFSET $off_s
");

$accStmt = $db->prepare("
    SELECT a.id,a.brand,a.name,a.model,a.price op,p.discount_percent,'' img,a.stock
      FROM promotions p
      JOIN accessories a ON p.product_type='accessory' AND p.product_id=a.id
     WHERE p.is_active=1 AND p.start_date<=CURDATE() AND (p.end_date>=CURDATE() OR p.end_date IS NULL)
     ORDER BY p.start_date DESC
     LIMIT ? OFFSET ?
");
$accStmt->bind_param('ii',$pp,$off_a);
$accStmt->execute();
$accessories = $accStmt->get_result();

$activeTab = isset($_GET['tab']) && $_GET['tab']==='acc' ? 'acc' : 'phones';
?>
<section class="catalog" id="catalog">
    <div class="container">
        <div class="catalog-header">
            <div class="header-left"><h2>Акции</h2></div>
            <div class="header-right">
                <div class="category-switcher">
                    <a href="#" data-tab="phones" class="category-link <?= $activeTab==='phones'?'active-category':'' ?>">Смартфоны</a>
                    <a href="#" data-tab="acc"    class="category-link <?= $activeTab==='acc'?'active-category':'' ?>">Аксессуары</a>
                </div>
            </div>
        </div>

        <div id="tab-phones" class="catalog-items <?= $activeTab==='phones'?'':'d-none' ?>">
            <?php if($phones->num_rows): ?>
            <div class="row">
                <?php while($r=$phones->fetch_assoc()): $new=$r['op']*(100-$r['discount_percent'])/100;?>
                <div class="col-md-4 mb-4">
                    <div class="accessory-card" onclick="location.href='product.php?id=<?= $r['id'] ?>'">
                        <?php if($r['img']):?><div class="accessory-image"><img src="<?= htmlspecialchars($r['img']) ?>"></div><?php endif;?>
                        <div class="accessory-info">
                            <h3><?= htmlspecialchars($r['brand'].' '.$r['name']) ?></h3>
                            <p class="accessory-model"><?= htmlspecialchars($r['model']) ?></p>
                            <p class="accessory-price"><del><?= number_format($r['op'],0,',',' ') ?> руб.</del><br><?= number_format($new,0,',',' ') ?> руб.<span class="badge bg-danger">-<?= $r['discount_percent'] ?>%</span></p>
                            <p class="accessory-stock">В наличии: <?= $r['stock'] ?> шт.</p>
                        </div>
                    </div>
                </div>
                <?php endwhile;?>
            </div>
            <?php else: ?><p class="no-items">Нет акций.</p><?php endif;?>
            <?php if($tp_s>1): ?>
            <div class="pagination-container"><nav><ul class="pagination">
                <?php if($page_s>1): ?><li class="page-item"><a class="page-link" href="stocks.php?page_s=<?= $page_s-1 ?>&page_a=<?= $page_a ?>&tab=phones">«</a></li><?php endif;?>
                <?php for($i=1;$i<=$tp_s;$i++):?><li class="page-item <?= $i==$page_s?'active':'' ?>"><a class="page-link" href="stocks.php?page_s=<?= $i ?>&page_a=<?= $page_a ?>&tab=phones"><?= $i ?></a></li><?php endfor;?>
                <?php if($page_s<$tp_s): ?><li class="page-item"><a class="page-link" href="stocks.php?page_s=<?= $page_s+1 ?>&page_a=<?= $page_a ?>&tab=phones">»</a></li><?php endif;?>
            </ul></nav></div>
            <?php endif;?>
        </div>

        <div id="tab-acc" class="catalog-items <?= $activeTab==='acc'?'':'d-none' ?>">
            <?php if($accessories->num_rows): ?>
            <div class="row">
                <?php while($r=$accessories->fetch_assoc()): $new=$r['op']*(100-$r['discount_percent'])/100;?>
                <div class="col-md-4 mb-4">
                    <div class="accessory-card" onclick="location.href='accessory.php?id=<?= $r['id'] ?>'">
                        <div class="accessory-info">
                            <h3><?= htmlspecialchars($r['brand'].' '.$r['name']) ?></h3>
                            <p class="accessory-model"><?= htmlspecialchars($r['model']) ?></p>
                            <p class="accessory-price"><del><?= number_format($r['op'],0,',',' ') ?> руб.</del><br><?= number_format($new,0,',',' ') ?> руб.<span class="badge bg-danger">-<?= $r['discount_percent'] ?>%</span></p>
                            <p class="accessory-stock">В наличии: <?= $r['stock'] ?> шт.</p>
                        </div>
                    </div>
                </div>
                <?php endwhile;?>
            </div>
            <?php else: ?><p class="no-items">Нет акций.</p><?php endif;?>
            <?php if($tp_a>1): ?>
            <div class="pagination-container"><nav><ul class="pagination">
                <?php if($page_a>1): ?><li class="page-item"><a class="page-link" href="stocks.php?page_a=<?= $page_a-1 ?>&page_s=<?= $page_s ?>&tab=acc">«</a></li><?php endif;?>
                <?php for($i=1;$i<=$tp_a;$i++):?><li class="page-item <?= $i==$page_a?'active':'' ?>"><a class="page-link" href="stocks.php?page_a=<?= $i ?>&page_s=<?= $page_s ?>&tab=acc"><?= $i ?></a></li><?php endfor;?>
                <?php if($page_a<$tp_a): ?><li class="page-item"><a class="page-link" href="stocks.php?page_a=<?= $page_a+1 ?>&page_s=<?= $page_s ?>&tab=acc">»</a></li><?php endif;?>
            </ul></nav></div>
            <?php endif;?>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.category-link').forEach(l=>{
    l.addEventListener('click',e=>{
        e.preventDefault();
        const tab=e.currentTarget.dataset.tab;
        document.getElementById('tab-phones').classList.toggle('d-none',tab!=='phones');
        document.getElementById('tab-acc').classList.toggle('d-none',tab!=='acc');
        document.querySelectorAll('.category-link').forEach(a=>a.classList.toggle('active-category',a.dataset.tab===tab));
        const params=new URLSearchParams(window.location.search);
        params.set('tab',tab);
        history.replaceState(null,'','stocks.php?'+params.toString());
    });
});
</script>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
