<?php
include 'config.php';
include 'cloudinary.php';
include 'navbar.php';
$pagina_atual = "eliminar";
include "sidebar.php";

if (!isset($_SESSION['rank']) || $_SESSION['rank'] !== 'admin') {
    header("Location: index.php");
    exit;
}

use Cloudinary\Api\Admin\AdminApi;

$adminApi = new AdminApi();

// Buscar obras
$obras = buscar_obra($ligaDB);

// Se uma obra foi selecionada, buscar os seus capitulos
$capitulos = [];
$id_manga_selecionado = $_GET['id_manga'] ?? $_POST['id_manga'] ?? null;
if ($id_manga_selecionado) {
    $capitulos = buscar_capitulos_manga($ligaDB, $id_manga_selecionado,null,'array');
}
$obra_sem_capitulos = $id_manga_selecionado && empty($capitulos);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_manga = intval($_POST['id_manga'] ?? 0);

    $link = buscar_obra_mais($ligaDB, $id_manga)["link"] ?? null; 

    // Eliminar obra completa
    if (isset($_POST["acao"]) && $_POST["acao"] === "eliminar_obra") {
        
        //Busca todas as páginas da obra
        $result_imgs = buscar_pagina($ligaDB,$id_manga,null,"array");
        
        //Busca a capa da obra se existir
        $result_capa = buscar_obra_mais($ligaDB,$id_manga)["capa"];  

        // Elimina do Cloudinary a capa e as páginas da obra selecionada
        try {
          // 1. Deletar páginas por prefixo da obra
          $prefixoPaginas = "mangas/$link"; // Ex: mangas/solo-leveling
          $adminApi->deleteAssetsByPrefix($prefixoPaginas, [
            "resource_type" => "image"
          ]);

          if (!empty($result_capa)) {
            $publicIdCapa = extrairPublicId($result_capa);
            if ($publicIdCapa) {
              $adminApi->deleteAssets([$publicIdCapa], [
                "resource_type" => "image"
              ]);
            }
          }
        } catch (Exception $e) {
          error_log("Erro ao apagar assets com prefixo: " . $e->getMessage());
        }



    $delete = eliminar($ligaDB,$id_manga, "obra");
        if ($delete === true) {
            $_SESSION['mensagem'] = "<p class = 'sucesso'> Obra eliminada com sucesso!";
        } else {
            $_SESSION['mensagem'] = "<p class = 'erro'> Erro ao eliminar a obra.";
        }
        header("Location: eliminar.php");
        exit;

    }

    // Eliminar capítulos selecionados
    elseif (isset($_POST["acao"]) && $_POST["acao"] === "eliminar_capitulos") {
        foreach ($_POST['capitulos'] as $id_capitulo) {
            $id_capitulo = intval($id_capitulo);

            $num_capitulo = buscar_capitulos_manga($ligaDB, null, $id_capitulo, 'normal')["num_capitulo"];
            $link = buscar_obra_mais($ligaDB, $id_manga_selecionado)["link"];
          
              try {
                $prefixo = "mangas/$link/capitulo-$num_capitulo/";
                $adminApi->deleteAssetsByPrefix($prefixo, [
                    "resource_type" => "image"
                ]);
            } catch (Exception $e) {
                error_log("Erro ao apagar prefixo $prefixo: " . $e->getMessage());
            }
    
            $delete_capitulo = eliminar($ligaDB,$id_capitulo,"capitulo");
    
            $_SESSION['mensagem'] = "<p class = 'sucesso'>Capítulo(s) eliminado(s) com sucesso.</p>";
        }
        header("Location: eliminar.php");
        exit;

    }
}
?>




<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eliminar</title>
  <link rel="stylesheet" href="css/eliminar.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
  <div class="container">
    <?php if (isset($_SESSION['mensagem'])): ?>
      <div class="mensagem"><?php echo $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?></div>
    <?php endif; ?>

    <h2 class="titulo-pagina"> Eliminar</h2>

    <form method="POST" id="form-eliminar">
    <input type="hidden" name="acao" id="acao" value="">
      <input type="hidden" name="id_manga" value="<?php echo $id_manga_selecionado ?>" required>
      

      <h3>Selecione uma Obra</h3>
      <input type="text" id="filtro-obras" placeholder="Pesquisar obras" class="barra-pesquisa">
      <div id="obras-container" class="obras-grid">
        <?php foreach ($obras as $obra): ?>
          <label class="obra-card <?php echo $id_manga_selecionado == $obra['id_manga'] ? 'selected' : '' ?>" data-titulo="<?php echo strtolower($obra['titulo']) ?>" style="display: none;">
            <input type="radio" name="id_manga" value="<?php echo $obra['id_manga'] ?>" style="display: none">
            <img src="<?php echo $obra['capa'] ?>" alt="<?php echo $obra['titulo'] ?>">
            <span><?php echo $obra['titulo'] ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="navegacao">
              <button type="button" id="anterior"><i class="bi bi-arrow-left"></i></button>
              <span id="pagina-atual">1</span>
              <button type="button" id="proximo"><i class="bi bi-arrow-right"></i></button>
          </div>

      <?php if ($obra_sem_capitulos): ?>
        <div class="alert-info">Esta obra não tem capítulos disponíveis.</div>
        <div class="acoes">
          <button type="button" name="eliminar_obra" onclick="prepararConfirmacao('eliminar_obra')">Eliminar Obra</button>
        </div>
      <?php elseif (!empty($capitulos)): ?>
        <div class="dropdown-container" id="dropdown-capitulos">
          <div class="dropdown-button" onclick="toggleDropdown()">Selecionar Capítulos</div>
          <div class="dropdown-list">
            <label><input type="checkbox" id="selecionar-todos" onchange="selecionarTodos(this)"> Selecionar Todos</label>
            <?php foreach ($capitulos as $cap): ?>
              <label>
                <input type="checkbox" name="capitulos[]" value="<?php echo $cap['id_capitulos'] ?>">
                Capítulo <?php echo $cap['num_capitulo'] ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="acoes">
        <button type="button" name="eliminar_capitulos" onclick="prepararConfirmacao('eliminar_capitulos')">Eliminar Capítulos</button>
        <button type="button" name="eliminar_obra" onclick="prepararConfirmacao('eliminar_obra')">Eliminar Obra</button>
        </div>
        <?php endif; ?>

       <!-- Modal de Confirmação -->
      <div class="modal-confirmar" id="modal-confirmar">
        <div class="modal-caixa">
          <p id="modal-mensagem">Tens a certeza que queres eliminar?</p>
            <div class="modal-botoes">
              <button type="submit" class="confirmar" id="modal-sim">Sim</button>
              <button type="button" class="cancelar" id="modal-nao">Cancelar</button>
            </div>
          </div>
        </div>
    </form>


    <!-- Spinner de loading -->
    <div id="loading-spinner" class="loading-spinner" style="display: none;"></div>
  </div>

<script>
let acaoConfirmar = false;

// Toggle dropdown
function toggleDropdown() {
  document.getElementById('dropdown-capitulos').classList.toggle('active');
}

document.addEventListener("DOMContentLoaded", function () {
        const obras = Array.from(document.querySelectorAll(".obra-card"));
        const obrasContainer = document.getElementById("obras-container");
        const input = document.getElementById("filtro-obras");
        const btnAnterior = document.getElementById("anterior");
        const btnProximo = document.getElementById("proximo");
        const spanPagina = document.getElementById("pagina-atual");

        let pagina = 1;
        const porPagina = 7;
        let obrasFiltradas = obras;

        function atualizarLista() {
        const inicio = (pagina - 1) * porPagina;
        const fim = inicio + porPagina;

        // Oculta todos os cards
        obras.forEach(card => card.style.display = "none");

        // Mostra os da página atual
        obrasFiltradas.slice(inicio, fim).forEach(card => card.style.display = "block");

        // Atualiza número
        spanPagina.textContent = pagina;

        // Desabilita ou ativa os botões conforme necessário
        btnAnterior.disabled = pagina === 1;
        btnProximo.disabled = pagina * porPagina >= obrasFiltradas.length;

        // Aplica/remover classe para visual desativado
        btnAnterior.classList.toggle("disabled-btn", btnAnterior.disabled);
        btnProximo.classList.toggle("disabled-btn", btnProximo.disabled);
        }


        function aplicarFiltro() {
        const termo = input.value.toLowerCase();
        obrasFiltradas = obras.filter(card => card.dataset.titulo.includes(termo));

        // Verifica se há obra selecionada e posiciona na página correta
        const selecionado = document.querySelector('.obra-card.selected');
        if (selecionado) {
            const indexSelecionado = obrasFiltradas.indexOf(selecionado);
            if (indexSelecionado !== -1) {
            pagina = Math.floor(indexSelecionado / porPagina) + 1;
            } else {
            pagina = 1; // Se a obra selecionada for excluída pelo filtro
            }
        } else {
            pagina = 1;
        }

        atualizarLista();
        }


        btnAnterior.addEventListener("click", () => {
            if (pagina > 1) {
            pagina--;
            atualizarLista();
            }
        });

        btnProximo.addEventListener("click", () => {
            if (pagina * porPagina < obrasFiltradas.length) {
            pagina++;
            atualizarLista();
            }
        });

        input.addEventListener("input", aplicarFiltro);

        aplicarFiltro(); // inicializa
        });

// Selecionar todos os capítulos
function selecionarTodos(checkbox) {
  const checkboxes = document.querySelectorAll('.dropdown-list input[type="checkbox"]:not(#selecionar-todos)');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

// Definir ação e mostrar modal com mensagem
function prepararConfirmacao(acao) {
  const modal = document.getElementById('modal-confirmar');
  const mensagem = document.getElementById('modal-mensagem');
  const tituloObra = document.querySelector('.obra-card.selected span')?.textContent || 'a obra';
  const capitulosSelecionados = Array.from(document.querySelectorAll('input[name="capitulos[]"]:checked'));
  const totalCapitulos = document.querySelectorAll('input[name="capitulos[]"]').length;

  if (acao === 'eliminar_obra') {
    if (capitulosSelecionados.length > 0) {
      mensagem.textContent = `Tens a certeza que queres eliminar a obra "${tituloObra}" e os seus capítulos?`;
    } else if (totalCapitulos > 0) {
      mensagem.textContent = `Tens a certeza que queres eliminar a obra "${tituloObra}" com ${totalCapitulos} capítulos?`;
    } else {
      mensagem.textContent = `Queres mesmo eliminar a obra "${tituloObra}" sem capítulos?`;
    }
  }

  if (acao === 'eliminar_capitulos') {
    if (capitulosSelecionados.length === 0) {
      alert("Seleciona pelo menos um capítulo para eliminar.");
      return false;
    }

    const nums = capitulosSelecionados.map(cb =>
      cb.parentElement.textContent.trim().replace('Capítulo ', '')
    );
    mensagem.textContent = `Tens a certeza que queres eliminar os ${nums.length} capítulo(s) (${nums.join(', ')}) da obra "${tituloObra}"?`;
  }
  document.getElementById('acao').value = acao;
  modal.style.display = 'flex';
  acaoConfirmar = false;
  return false;
}

// Botão "Sim" dentro do modal - permite submit
document.getElementById('modal-sim').addEventListener('click', () => {
  acaoConfirmar = true;
  document.getElementById('modal-confirmar').style.display = 'none';
});

// Botão "Cancelar" - fecha modal sem submit
document.getElementById('modal-nao').addEventListener('click', () => {
  document.getElementById('modal-confirmar').style.display = 'none';
  acaoConfirmar = false;
  document.getElementById('acao').value = '';
  document.querySelectorAll('input[name="capitulos[]"]').forEach(cb => cb.checked = false);

});

// Submit final controlado
document.getElementById('form-eliminar').addEventListener('submit', function (e) {
  const modalAberto = document.getElementById('modal-confirmar').style.display === 'flex';
  const acao = document.getElementById('acao').value;


  if (modalAberto || (acao && !acaoConfirmar)) {
    e.preventDefault(); // cancela qualquer submissão não confirmada
    return;
  }
  // Desativa botões
  this.querySelectorAll('button').forEach(btn => btn.disabled = true);
   // Desativa a seleção de obra
   document.querySelectorAll('.obra-card input[type="radio"]').forEach(inp => inp.disabled = true);
});

// Ao mudar a obra, submete apenas se não há modal nem ação pendente
document.querySelectorAll('input[name="id_manga"]').forEach(radio => {
  radio.addEventListener('change', function () {
    const modalAberto = document.getElementById('modal-confirmar').style.display === 'flex';
    const acao = document.getElementById('acao').value;

    // Só permite mudar de obra se não estiver em estado de confirmação
    if (!modalAberto && !acao) {
      document.getElementById('form-eliminar').submit();
    }
  });
});

</script>

</body>
</html>