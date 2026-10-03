<?php
/**
 * Rotina de desinstalação.
 *
 * Este plugin nunca grava capabilities, opções ou dados na base de
 * dados — toda a concessão de acesso (incluindo a elevação temporária
 * a manage_options) é feita dinamicamente através do filtro
 * 'user_has_cap', apenas durante pedidos verificados do
 * Customizer/Kadence. Por isso, desinstalar o plugin não requer
 * qualquer limpeza: ao remover o plugin, o filtro deixa simplesmente
 * de ser executado e a role "editor" volta de imediato ao seu
 * comportamento nativo.
 *
 * Este ficheiro existe apenas para cumprir as boas práticas do
 * WordPress.org (evita o aviso "no uninstall.php found") e para deixar
 * explícito, para quem revê o plugin, que não há nada a limpar.
 *
 * @package Editor_Theme_Access_For_Kadence
 */

// Se o WordPress não invocou este ficheiro via processo de desinstalação, sai.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Nada a fazer: nenhuma opção, capability ou meta é criada por este plugin.
