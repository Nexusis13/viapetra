<?php
require_once '../config/protect.php';
require_once '../config/config.php';
require_once '../views/header.php';
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';

?>
<script>
// Fun��o para atualizar a sauda��o baseada no hor�rio
function updateGreeting() {
    const now = new Date();
    const currentHour = now.getHours();
    const welcomeTitleElement = document.querySelector('.welcome-title');
    
    // Nome do usu�rio (j� est� no HTML via PHP)
    const userName = '<?= $_SESSION['usuario_nome'] ?? 'Usu�rio'; ?>';
    
    // Atualizar sauda��o baseada no hor�rio
    if (currentHour >= 5 && currentHour < 12) {
        welcomeTitleElement.innerHTML = `&#9728; Bom Dia, ${userName}! &#127749;`;
    } else if (currentHour >= 12 && currentHour < 18) {
        welcomeTitleElement.innerHTML = `&#9925; Boa Tarde, ${userName}! &#9728;`;
    } else {
        welcomeTitleElement.innerHTML = `&#127769; Boa Noite, ${userName}! &#10024;`;
    }
}

// Executar quando a p�gina carregar
document.addEventListener('DOMContentLoaded', function() {
    updateGreeting();
});

// Atualizar a cada minuto (opcional)
setInterval(updateGreeting, 60000);

// Atualizar quando a p�gina ganha foco
window.addEventListener('focus', updateGreeting);
</script>
<div class="container mt-4">
    <h2>Bem-vindo ao Painel</h2>
    <?php
// Dados estatísticos
$agendados = $pdo->query("
    SELECT COUNT(*) FROM boleto WHERE status = 'A vencer'
")->fetchColumn();

// Resumo de Vendas
$sqlResumoVendas = "SELECT 
                        COUNT(*) as total_vendas,
                        SUM(vlr_total) as soma_total,
                        SUM(vlr_entrada) as soma_entrada
                      FROM vendas v";
$stmtResumoVendas = $pdo->prepare($sqlResumoVendas);
$stmtResumoVendas->execute();
$resumoVendas = $stmtResumoVendas->fetch();
?><?php
require_once '../config/protect.php';
require_once '../config/config.php';
require_once '../views/header.php';

$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);
        --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        --weather-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
        --card-hover-shadow: 0 20px 40px rgba(0,0,0,0.15);
        --border-radius: 20px;
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
    }

    .dashboard-container {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .welcome-header {
        background: var(--primary-gradient);
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
    }

    .welcome-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(5deg); }
    }

    .welcome-content {
        position: relative;
        z-index: 1;
    }

    .welcome-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .welcome-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .weather-card {
        background: var(--weather-gradient);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: #333;
    }

    .weather-card::before {
        content: '☀️';
        position: absolute;
        top: -10px;
        right: -10px;
        font-size: 8rem;
        opacity: 0.1;
        animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .weather-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
    }

    .weather-info {
        position: relative;
        z-index: 1;
    }

    .temperature {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .location {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: #34495e;
    }

    .country {
        font-size: 1rem;
        opacity: 0.8;
        color: #7f8c8d;
    }

    .boletos-card {
        background: var(--success-gradient);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .boletos-card::before {
        content: '📊';
        position: absolute;
        top: -20px;
        right: -20px;
        font-size: 6rem;
        opacity: 0.2;
    }

    .boletos-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
    }

    .boletos-title {
        font-size: 1.1rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }

    .boletos-count {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 0;
    }

    .sales-summary {
        background: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--card-shadow);
        margin-top: 2rem;
    }

    .sales-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 2rem;
        color: #2c3e50;
        text-align: center;
        position: relative;
    }

    .sales-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: 2px;
    }

    .sales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .metric-card {
        border-radius: var(--border-radius);
        padding: 2rem;
        text-align: center;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: white;
    }

    .metric-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
        animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
    }

    .metric-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--card-hover-shadow);
    }

    .metric-card.success { background: var(--success-gradient); }
    .metric-card.info { background: var(--info-gradient); color: #333; }
    .metric-card.warning { background: var(--warning-gradient); }
    .metric-card.danger { background: var(--danger-gradient); }

    .metric-title {
        font-size: 1rem;
        margin-bottom: 1rem;
        opacity: 0.9;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }

    .metric-value {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 0;
        position: relative;
        z-index: 1;
    }

    .metric-icon {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 2rem;
        opacity: 0.3;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 1rem;
        }
        
        .welcome-title {
            font-size: 2rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .sales-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        
        .temperature {
            font-size: 2.5rem;
        }
        
        .metric-value {
            font-size: 1.8rem;
        }
    }

    .loading-skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<div class="dashboard-container">
    <?php
    // Dados estatísticos
    $agendados = $pdo->query("
        SELECT COUNT(*) FROM boleto WHERE status = 'A vencer'
    ")->fetchColumn();

    // Resumo de Vendas
    $sqlResumoVendas = "SELECT 
                            COUNT(*) as total_vendas,
                            SUM(vlr_total) as soma_total,
                            SUM(vlr_entrada) as soma_entrada
                          FROM vendas v";
    $stmtResumoVendas = $pdo->prepare($sqlResumoVendas);
    $stmtResumoVendas->execute();
    $resumoVendas = $stmtResumoVendas->fetch();
    ?>

    <!-- Header de Boas-vindas -->
    <div class="welcome-header">
    <div class="welcome-content">
        <h1 class="welcome-title">Ol�, <?= $_SESSION['usuario_nome']; ?>! </h1>
        <p class="welcome-subtitle" id="MensagemClima">Carregando informa��es do clima...</p>
    </div>
</div>

    <!-- Cards de Estatísticas Principais -->
    <div class="stats-grid">
        <div class="weather-card">
            <div class="weather-info">
                <div class="temperature" id="temperaturaHtml">--°C</div>
                <div class="location" id="Cidade">Carregando...</div>
                <div class="country" id="Pais">--</div>
            </div>
        </div>

        <div class="boletos-card">
            <div class="metric-icon">📋</div>
            <div class="boletos-title">Boletos Agendados</div>
            <div class="boletos-count"><?= $agendados ?? 0 ?></div>
        </div>
    </div>

    <!-- Resumo de Vendas -->
    <div class="sales-summary">
        <h2 class="sales-title">📈 Resumo de Vendas</h2>
        <div class="sales-grid">
            <div class="metric-card success">
                <div class="metric-icon">🎯</div>
                <div class="metric-title">Total de Vendas</div>
                <div class="metric-value"><?= $resumoVendas['total_vendas'] ?? 0 ?></div>
            </div>
            
            <div class="metric-card info">
                <div class="metric-icon">💰</div>
                <div class="metric-title">Valor Total</div>
                <div class="metric-value">R$ <?= number_format($resumoVendas['soma_total'] ?? 0, 2, ',', '.') ?></div>
            </div>
            
            <div class="metric-card warning">
                <div class="metric-icon">💳</div>
                <div class="metric-title">Total Entradas</div>
                <div class="metric-value">R$ <?= number_format($resumoVendas['soma_entrada'] ?? 0, 2, ',', '.') ?></div>
            </div>
            
            <div class="metric-card danger">
                <div class="metric-icon">⏰</div>
                <div class="metric-title">Saldo a Receber</div>
                <div class="metric-value">R$ <?= number_format(($resumoVendas['soma_total'] ?? 0) - ($resumoVendas['soma_entrada'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<script>
async function buscarClima() {
    const apiKey = '6f6a9a2e8c39badaeff32ec696c15707';
    const cidade = 'Rio Verde,BR';
    const url = `https://api.openweathermap.org/data/2.5/weather?q=${cidade}&appid=${apiKey}&units=metric&lang=pt_br`;

    try {
        const response = await fetch(url);
        const dados = await response.json();

        // Animação suave para mudança de valores
        document.getElementById("temperaturaHtml").style.opacity = '0';
        document.getElementById("Cidade").style.opacity = '0';
        document.getElementById("Pais").style.opacity = '0';
        document.getElementById("MensagemClima").style.opacity = '0';

        setTimeout(() => {
            document.getElementById("temperaturaHtml").textContent = Math.round(dados.main.temp) + '°C';
            document.getElementById("Cidade").textContent = dados.name;
            document.getElementById("Pais").textContent = dados.sys.country;
            
            // Personalizar mensagem baseada no clima
            const descricao = dados.weather[0].description;
            const emoji = getWeatherEmoji(dados.weather[0].main);
            document.getElementById("MensagemClima").textContent = `${emoji} ${descricao.charAt(0).toUpperCase() + descricao.slice(1)} - Tenha um ótimo dia!`;
            
            // Fade in
            document.getElementById("temperaturaHtml").style.opacity = '1';
            document.getElementById("Cidade").style.opacity = '1';
            document.getElementById("Pais").style.opacity = '1';
            document.getElementById("MensagemClima").style.opacity = '1';
        }, 300);

    } catch (error) {
        console.error('Erro ao buscar clima:', error);
        document.getElementById("MensagemClima").textContent = "🌟 Seja bem-vindo! Tenha um excelente dia de trabalho!";
        document.getElementById("temperaturaHtml").textContent = "--°C";
        document.getElementById("Cidade").textContent = "Rio Verde";
        document.getElementById("Pais").textContent = "BR";
    }
}

function getWeatherEmoji(weatherMain) {
    const weatherEmojis = {
        'Clear': '☀️',
        'Clouds': '☁️',
        'Rain': '🌧️',
        'Drizzle': '🌦️',
        'Thunderstorm': '⛈️',
        'Snow': '❄️',
        'Mist': '🌫️',
        'Fog': '🌫️',
        'Haze': '🌫️'
    };
    return weatherEmojis[weatherMain] || '🌤️';
}

// Adicionar transições suaves nos elementos
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.weather-card, .boletos-card, .metric-card');
    elements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        setTimeout(() => {
            el.style.transition = 'all 0.6s ease';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Buscar clima ao carregar a página
buscarClima();

// Atualizar clima a cada 10 minutos
setInterval(buscarClima, 600000);
</script>

<?php require_once '../views/footer.php'; ?>
</script>

<?php require_once '../views/footer.php'; ?>