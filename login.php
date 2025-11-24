<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

include 'includes/conexao.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Agora, busca só usuários tipo admin
    $sql = "SELECT id, nome_completo, senha FROM usuarios WHERE email = ? AND tipo_usuario = 'admin'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $dados = $resultado->fetch_assoc();
        // Atenção: md5 para compatibilidade com o restante do sistema,
        // troque para password_hash/password_verify se você atualizar!
        if (md5($senha) === $dados['senha']) { 
            $_SESSION['usuario_id'] = $dados['id'];
            $_SESSION['nome_usuario'] = $dados['nome_completo'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            $erro = "Senha incorreta.";
        }
    } else {
        $erro = "Usuário não encontrado ou você não possui perfil de administrador.";
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Projeto Caminhões - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css"> 
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { text-align: center; }
        .input-icone-wrapper { position: relative; margin-bottom: 15px; text-align: left; }
        .input-icone-wrapper i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #63b1e3; font-size: 1.1em; pointer-events: none; }
        .login-container input[type="email"], .login-container input[type="password"] { padding: 15px 15px 15px 45px; box-sizing: border-box; width: 100%; }
        .perfil-icone-wrapper { position: absolute; top: -40px; left: 50%; transform: translateX(-50%); width: 80px; height: 80px; background-color: white; border: 3px solid #63b1e3; border-radius: 50%; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);}
        .perfil-icone-wrapper i { position: static; color: #63b1e3; font-size: 2.2em; transform: none; }
        .login-container form { margin-top: 30px; }
        .login-container p { color: #c0392b; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .link-alinhamento-direita { text-align: right; margin-top: -10px; margin-bottom: 25px; }
        .link-alinhamento-direita .link-esqueci-senha { color: #63b1e3; text-decoration: none; font-size: 0.9em; font-weight: bold; transition: color 0.3s;}
        .link-alinhamento-direita .link-esqueci-senha:hover { color: #559ecf; text-decoration: underline;}
    </style>
</head>
<body>
    <div class="background-overlay"></div> 
    <div class="login-container" style="position: relative;">
        <div class="perfil-icone-wrapper">
             <i class="fa-solid fa-user"></i> 
        </div>
        <?php if (isset($erro)) { echo "<p>$erro</p>"; } ?>
        <form action="login.php" method="POST">
            <div class="input-icone-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="email" name="email" placeholder="E-mail" required>
            </div>
            <div class="input-icone-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="senha" placeholder="Senha" required>
            </div>
            <div class="link-alinhamento-direita">
                 <a href="#" class="link-esqueci-senha">ESQUECI MINHA SENHA</a>
            </div>
            <button type="submit">LOGIN</button>
        </form>
    </div>
</body>
</html>