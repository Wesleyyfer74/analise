<?php $current_admin_page = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
<nav class="nav">
    <a class="<?= $current_admin_page === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
    <a class="<?= $current_admin_page === 'clientes.php' ? 'active' : '' ?>" href="clientes.php">Clientes</a>
    <a href="clientes.php?action=new">Novo cliente</a>
    <a class="<?= $current_admin_page === 'financeiro.php' ? 'active' : '' ?>" href="financeiro.php">Financeiro</a>
    <a class="<?= $current_admin_page === 'dona.php' ? 'active' : '' ?>" href="dona.php">Dona/Split</a>
    <a href="../index.php">Site</a>
    <a href="logout.php">Sair</a>
</nav>
