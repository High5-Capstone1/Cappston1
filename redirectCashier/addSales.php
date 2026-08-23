<?php

require_once '../session.php';
include '../DBconnect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../roleLogin/login.php");
    exit();
}
$cashier_id = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];


if (isset($_SESSION['success_message'])) {
    echo '<p class="success">' . $_SESSION['success_message'] . '</p>';
    unset($_SESSION['success_message']);
}

$productQuery = $conn->query("SELECT DISTINCT product_name FROM products ORDER BY product_name");

$allProducts = [];
$result = $conn->query("
    SELECT p.*,
           MIN(COALESCE(inv.quantity, 0)) AS stock,
           (
               SELECT it2.item_name
               FROM product_items pi2
               JOIN items it2 ON it2.item_id = pi2.item_id
               LEFT JOIN inventory inv2 ON inv2.item_id = pi2.item_id AND inv2.store_id = $store_id
               WHERE pi2.product_id = p.product_id
               AND COALESCE(inv2.quantity, 0) <= 0
               ORDER BY COALESCE(inv2.quantity, 0) ASC
               LIMIT 1
           ) AS out_of_item
    FROM products p
    LEFT JOIN product_items pi ON pi.product_id = p.product_id
    LEFT JOIN items it ON it.item_id = pi.item_id
    LEFT JOIN inventory inv ON inv.item_id = pi.item_id AND inv.store_id = $store_id
    WHERE p.status = 'active'
    GROUP BY p.product_id
");
while ($row = $result->fetch_assoc()) {
    $allProducts[] = $row;
}
$toppingsQuery = $conn->query("SELECT * FROM toppings WHERE status='active'");

if (isset($_POST['add_to_cart'])) {
    $product_id  = $_POST['product_id'];
    $quantity    = $_POST['quantity'];
    $topping_qty = $_POST['topping_qty'] ?? [];

    $product = $conn->query("SELECT product_name, size, price FROM products WHERE product_id = $product_id")->fetch_assoc();

    $toppings = [];
    $topping_total = 0;

    foreach ($topping_qty as $tid => $qty) {
        $t = $conn->query("SELECT topping_name, price FROM toppings WHERE topping_id = $tid")->fetch_assoc();
        $subtotal = $t['price'] * $qty;
        $toppings[] = [
            'topping_id' => $tid,
            'name'       => $t['topping_name'],
            'price'      => $t['price'],
            'qty'        => $qty,
            'subtotal'   => $subtotal
        ];
        $topping_total += $subtotal;
    }

    $subtotal = ($product['price'] * $quantity) + $topping_total;
    $_SESSION['cart'][] = [
        'product_id'   => $product_id,
        'product_name' => $product['product_name'],
        'size'         => $product['size'],
        'price'        => $product['price'],
        'quantity'     => $quantity,
        'toppings'     => $toppings,
        'subtotal'     => $subtotal
    ];

    header("Location: addSales.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier - Add Sale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../Design/forAddSales.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

<header class="header">
    <div class="header-container">
        <div class="header-content">
            <div class="header-left">
                <a href="cashierDashboard.php" class="back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="header-title">
                    <h1><i class="fa-solid fa-cart-shopping"></i> Sales</h1>
                    <p>Start recording sale</p>
                </div>
            </div>
            <div class="header-right">
                <p>Store #<?= htmlspecialchars($store_id) ?></p>
                <p class="username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></p>
            </div>
        </div>
    </div>
</header>

<div class="page-wrapper">
    <div class="page-header">
        <div class="brand">
            <img src="../../img/mrsofty1.png" alt="Mr. Softy">
            <h2>Choose a Product</h2>
        </div>
        <a href="vericationOrder.php" class="cart-btn">
            <i class="fas fa-shopping-cart"></i>
            View Order
            <span class="cart-badge"><?= count($_SESSION['cart'] ?? []) ?></span>
        </a>
    </div>

    <div class="section-title"><i class="fas fa-ice-cream"></i> Signature Creations</div>
    <?php
    $grouped = [];
    foreach ($allProducts as $p) {
        $grouped[$p['product_name']][] = $p;
    }
    $productImages = [
        'Choco Glaze'        => '../img/choco_glaze.jpg',
        'Rainbow Delight'    => '../img/rainbow_delight.jpg',
        'Cookie Monster'     => '../img/cookie_monster.jpg',
        'Nutty Salted Caramel' =>'../img/salted_caramel.jpg',
        'Triple Choco'       => '../img/triple_choco.jpg',
        'Sundae Overload'    => '../img/sundae_overload.jpg',
        'Kiddie'             => '../img/Cone.jpg',
        'Giant'              => '../img/Cone.jpg',
    ];
    ?>
    <div class="products-grid">
        <?php foreach ($grouped as $name => $sizes): ?>
            <?php $imgSrc = $productImages[$name] ?? ''; ?>
            <div class="product-card" data-product="<?= htmlspecialchars($name) ?>">
                <div class="selected-check"><i class="fas fa-check"></i></div>
                <div class="product-img-wrap">
                    <?php if ($imgSrc): ?>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($name) ?>"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="no-img" style="display:none;"><i class="fas fa-ice-cream"></i></span>
                    <?php else: ?>
                        <span class="no-img"><i class="fas fa-ice-cream"></i></span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($name) ?></div>
                    <div class="size-options">
                        <?php foreach ($sizes as $s): ?>
                            <div class="size-option <?= $s['stock'] <= 0 ? 'out-of-stock' : '' ?>"
                                data-product-id="<?= $s['product_id'] ?>"
                                data-price="<?= $s['price'] ?>"
                                data-size="<?= htmlspecialchars($s['size']) ?>"
                                data-stock="<?= (int)$s['stock'] ?>"
                                data-out-of="<?= htmlspecialchars($s['out_of_item'] ?? '') ?>">
                               <span class="size-label"><?= htmlspecialchars($s['size']) ?></span>
                            <span class="size-price">
                                <?php if ($s['stock'] <= 0): ?>
                                    <span class="oos-pill" title="No <?= htmlspecialchars($s['out_of_item'] ?? 'stock') ?>">
                                        No <?= htmlspecialchars($s['out_of_item'] ?? 'Stock') ?>
                                    </span>
                                <?php else: ?>
                                    ₱<?= $s['price'] ?>
                                <?php endif; ?>
                            </span>
                          </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="qty-control">
                        <button type="button" class="qty-btn-card card-minus">−</button>
                        <span class="qty-display">0</span>
                        <button type="button" class="qty-btn-card card-plus">+</button>
                    </div>

                    <button type="button" class="add-card-btn" disabled>
                        <i class="fas fa-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="toppings-panel">
        <h3><span>🍰</span> Add Toppings <small style="font-weight:600;font-size:0.8rem;color:var(--text-muted);margin-left:6px;">(Optional – applies to next item added)</small></h3>
        <div class="toppings-grid">
            <?php
       
            $toppingsResult = $conn->query("SELECT * FROM toppings WHERE status='active'");
            while ($t = $toppingsResult->fetch_assoc()):
            ?>
                <div class="topping-card" data-tid="<?= $t['topping_id'] ?>">
                    <label class="topping-header">
                        <input type="checkbox" class="topping-check-input" data-id="<?= $t['topping_id'] ?>">
                        <div class="topping-checkbox-ui"><i class="fas fa-check"></i></div>
                        <span class="topping-name"><?= htmlspecialchars($t['topping_name']) ?></span>
                        <span class="topping-price">+₱<?= $t['price'] ?></span>
                    </label>
                    <div class="topping-qty">
                        <button type="button" class="qty-btn-card minus">−</button>
                        <span class="qty-display topping-qty-val">1</span>
                        <button type="button" class="qty-btn-card plus">+</button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>


<form id="addToCartForm" method="POST">
    <input type="hidden" name="add_to_cart" value="1">
    <input type="hidden" name="product_id" id="form_product_id">
    <input type="hidden" name="quantity" id="form_quantity">
    <div id="form_toppings"></div>
</form>

<script>
 
    function flyToViewOrder(sourceEl) {
        const cartBtn  = document.querySelector('.cart-btn');
        const badge    = cartBtn.querySelector('.cart-badge');
        const srcRect  = sourceEl.getBoundingClientRect();
        const tgtRect  = cartBtn.getBoundingClientRect();

        const flyEl = document.createElement('div');
        flyEl.className = 'fly-item';
        flyEl.innerHTML = '<i class="fas fa-ice-cream"></i>';

    
        const startX = srcRect.left + srcRect.width  / 2 - 18;
        const startY = srcRect.top  + srcRect.height / 2 - 18;

      
        const dx = (tgtRect.left + tgtRect.width  / 2 - 18) - startX;
        const dy = (tgtRect.top  + tgtRect.height / 2 - 18) - startY;

        flyEl.style.left = startX + 'px';
        flyEl.style.top  = startY + 'px';
        flyEl.style.setProperty('--fly-x', dx + 'px');
        flyEl.style.setProperty('--fly-y', dy + 'px');

        document.body.appendChild(flyEl);

        flyEl.addEventListener('animationend', () => {
            flyEl.remove();
        
            cartBtn.classList.remove('cart-animate');
            void cartBtn.offsetWidth; // reflow
            cartBtn.classList.add('cart-animate');
            cartBtn.addEventListener('animationend', () => cartBtn.classList.remove('cart-animate'), { once: true });

          
            badge.classList.remove('badge-pop');
            void badge.offsetWidth;
            badge.classList.add('badge-pop');
            badge.addEventListener('animationend', () => badge.classList.remove('badge-pop'), { once: true });
        });
    }

//size option
  document.querySelectorAll('.size-option').forEach(opt => {
    opt.addEventListener('click', function () {
        if (this.classList.contains('out-of-stock')) {
            const item = this.dataset.outOf || 'this item';
            showToast(`Cannot select — No ${item} in stock`);
            return;
        } // block OOS
        const card = this.closest('.product-card');
        const disp = card.querySelector('.qty-display');
        card.querySelectorAll('.size-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
        card.classList.add('selected');
        card.querySelector('.add-card-btn').disabled = false;
        if (parseInt(disp.textContent) === 0) disp.textContent = 1;
    });
});


    document.querySelectorAll('.product-card').forEach(card => {
        const minus = card.querySelector('.card-minus');
        const plus  = card.querySelector('.card-plus');
        const disp  = card.querySelector('.qty-display');

        minus.addEventListener('click', () => {
            let v = parseInt(disp.textContent);
            if (v > 1) {
                disp.textContent = v - 1;
            } else {
                disp.textContent = 0;
                card.querySelectorAll('.size-option').forEach(o => o.classList.remove('active'));
                card.classList.remove('selected');
                card.querySelector('.add-card-btn').disabled = true;
            }
        });
        plus.addEventListener('click', () => {
            disp.textContent = parseInt(disp.textContent) + 1;
        });

        card.querySelector('.add-card-btn').addEventListener('click', function () {
            const selected = card.querySelector('.size-option.active');
            if (!selected) return;


            flyToViewOrder(this);

            const productId = selected.dataset.productId;
            const quantity  = parseInt(card.querySelector('.qty-display').textContent);

            document.getElementById('form_product_id').value = productId;
            document.getElementById('form_quantity').value   = quantity;

            const toppingContainer = document.getElementById('form_toppings');
            toppingContainer.innerHTML = '';
            document.querySelectorAll('.topping-check-input:checked').forEach(inp => {
                const tid    = inp.dataset.id;
                const qtyVal = inp.closest('.topping-card').querySelector('.topping-qty-val').textContent;
                const hidden = document.createElement('input');
                hidden.type  = 'hidden';
                hidden.name  = `topping_qty[${tid}]`;
                hidden.value = qtyVal;
                toppingContainer.appendChild(hidden);
            });

   
            setTimeout(() => {
                document.getElementById('addToCartForm').submit();
            }, 900);
        });
    });


    document.querySelectorAll('.topping-card').forEach(card => {
        const inp = card.querySelector('.topping-check-input');
        card.querySelector('.topping-header').addEventListener('click', function () {
            inp.checked = !inp.checked;
            card.classList.toggle('checked', inp.checked);
        });

        const minus = card.querySelector('.minus');
        const plus  = card.querySelector('.plus');
        const val   = card.querySelector('.topping-qty-val');

        minus.addEventListener('click', () => {
            let v = parseInt(val.textContent);
            if (v > 1) val.textContent = v - 1;
        });
        plus.addEventListener('click', () => {
            val.textContent = parseInt(val.textContent) + 1;
        });
    });


    //showtoest 

    function showToast(msg) {
    let t = document.getElementById('oos-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'oos-toast';
        t.style.cssText = `
            position:fixed; bottom:28px; left:50%; transform:translateX(-50%);
            background:#ff4d6d; color:#fff; font-family:'Nunito',sans-serif;
            font-weight:700; font-size:0.95rem; padding:12px 28px;
            border-radius:50px; box-shadow:0 4px 20px rgba(255,77,109,0.4);
            z-index:9999; opacity:0; transition:opacity 0.3s;
            pointer-events:none; white-space:nowrap;
        `;
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._hide);
    t._hide = setTimeout(() => t.style.opacity = '0', 2500);
}
</script>
</body>
</html>