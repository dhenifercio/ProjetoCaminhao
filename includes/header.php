<?php
// Certifique-se de que a sessão já foi iniciada na página principal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUIR A CONEXÃO COM O BANCO DE DADOS
// É VITAL que 'includes/conexao.php' exista e defina a variável $conn
if (file_exists('includes/conexao.php')) {
    include 'includes/conexao.php';
}


// Lógica para obter o nome do usuário logado
$nome_usuario = "Visitante"; // Valor padrão

if (isset($_SESSION['usuario_id']) && isset($conn)) {
    $usuario_id = $_SESSION['usuario_id'];
    
    // 2. BUSCAR O NOME DO USUÁRIO NO BANCO DE DADOS
    $sql_nome = "SELECT nome_completo FROM usuarios WHERE id = ?";
    
    // Usando Prepared Statements para segurança
    $stmt = $conn->prepare($sql_nome);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nome_usuario = $row['nome_completo']; // Atualiza a variável com o nome real
    }
    
    $stmt->close();
    // NÃO FECHE A CONEXÃO AQUI, pois ela pode ser necessária para a página principal.

}


// Variável auxiliar para destacar o link ativo.
$pagina_atual = basename($_SERVER['PHP_SELF']);
$caminho_completo_atual = $_SERVER['PHP_SELF'];

?>
<div class="menu-lateral">
    
    <div class="perfil-usuario-menu">
        <img src="assets/perfil-padrao.jpg" alt="Ícone de Perfil" class="imagem-perfil"> 
        <p class="label-usuario">Usuário:<?php echo htmlspecialchars($nome_usuario); ?></p>
    </div>
    
    <ul>
        
        <li>
            <a href="dashboard.php" class="<?php echo ($pagina_atual == 'dashboard.php') ? 'ativo' : ''; ?>">
                <i class="fa-solid fa-gauge icone-menu"></i> 
                Dashboard
            </a>
        </li>

        <li>
            <a href="cadastro_motorista.php" class="<?php echo ($pagina_atual == 'cadastro_motorista.php') ? 'ativo' : ''; ?>">
                <i class="fa-solid fa-user-plus icone-menu"></i> 
                Cadastro de Motorista
            </a>
        </li>
        
        <?php 
            // Verifica se um dos subitens está ativo para manter o menu principal aberto
            $is_caminhao_ativo = (
                strpos($caminho_completo_atual, 'cadastro_carreta.php') !== false ||
                strpos($caminho_completo_atual, 'cadastro_cavalo.php') !== false ||
                strpos($caminho_completo_atual, 'cadastro_conjunto.php') !== false
            );
            $caminhao_classe = $is_caminhao_ativo ? 'ativo' : '';
            $caminhao_submenu_classe = $is_caminhao_ativo ? 'aberto' : '';
        ?>
        <li class="menu-com-submenu">
            <a href="javascript:void(0);" id="menu-caminhao-toggle" class="submenu-toggle <?php echo $caminhao_classe; ?>">
                <i class="fa-solid fa-truck-moving icone-menu"></i> 
                Cadastro de Caminhão 
                <i class="fa-solid fa-chevron-down seta-submenu <?php echo $is_caminhao_ativo ? 'rotacionada' : ''; ?>"></i>
            </a>
            
            <ul class="submenu-cadastro <?php echo $caminhao_submenu_classe; ?>">
                <li><a href="cadastro_carreta.php" class="<?php echo ($pagina_atual == 'cadastro_carreta.php') ? 'ativo' : ''; ?>">Cadastro de Carreta</a></li>
                <li><a href="cadastro_cavalo.php" class="<?php echo ($pagina_atual == 'cadastro_cavalo.php') ? 'ativo' : ''; ?>">Cadastro de Cavalo</a></li>
                <li><a href="cadastro_conjunto.php" class="<?php echo ($pagina_atual == 'cadastro_conjunto.php') ? 'ativo' : ''; ?>">Cadastro de Conjunto</a></li>
            </ul>
        </li>
        
        <li>
            <a href="fechamento.php" class="<?php echo ($pagina_atual == 'fechamento.php') ? 'ativo' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar icone-menu"></i>
                Fechamento
            </a>
        </li>
        
        <li>
            <a href="login.php">
                <i class="fa-solid fa-sign-out-alt icone-menu"></i>
                Sair
            </a>
        </li>

    </ul>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Encontra o toggle de CADASTRO DE CAMINHÃO
        const toggleCaminhao = document.getElementById('menu-caminhao-toggle');
        if (toggleCaminhao) {
            toggleCaminhao.addEventListener('click', function(e) {
                e.preventDefault(); 
                const submenu = this.closest('.menu-com-submenu').querySelector('.submenu-cadastro');
                const seta = this.querySelector('.seta-submenu');
                
                // Abre/Fecha o submenu
                submenu.classList.toggle('aberto');
                // Rotaciona a seta
                seta.classList.toggle('rotacionada');
            });
        }
    });
</script>