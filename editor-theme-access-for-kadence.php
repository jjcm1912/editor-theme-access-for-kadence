<?php
/**
 * Plugin Name:       Editor Theme Access for Kadence
 * Plugin URI:        https://exemplo.com/editor-theme-access-for-kadence
 * Description:       Dá à role "editor" acesso completo ao Customizer do tema Kadence (Header, Footer, Colors & Fonts, General, Posts/Pages Layout, etc.), que o Kadence só mostra integralmente a quem tem manage_options. A elevação só atua quando o tema ativo é mesmo o Kadence, apenas dentro de pedidos verificados (nonce) do Customizer, e nunca é escrita na base de dados.
 * Version:           2.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            José
 * Author URI:        https://exemplo.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       editor-theme-access-for-kadence
 * Domain Path:       /languages
 *
 * @package Editor_Theme_Access_For_Kadence
 */

// Impede o acesso direto ao ficheiro.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Evita redeclarações se o ficheiro for incluído mais que uma vez.
if ( ! class_exists( 'Editor_Theme_Access_For_Kadence' ) ) {

	/**
	 * Classe principal do plugin.
	 *
	 * O Kadence usa uma capability interna própria para decidir se
	 * mostra os seus painéis do Customizer (Header, Footer, Colors &
	 * Fonts, General, Posts/Pages Layout, Homepage Settings). Na
	 * prática, essa capability só é satisfeita por manage_options — a
	 * capability edit_theme_options sozinha não chega para ver esses
	 * painéis (continuam a aparecer os nativos do WordPress: Site
	 * Identity, Menus, Widgets, CSS Adicional).
	 *
	 * Para contornar isto sem conceder manage_options de forma ampla,
	 * este plugin concede-a apenas:
	 *   1) quando o tema ativo é mesmo o Kadence (ou um tema-filho) —
	 *      noutros temas, o plugin não faz absolutamente nada;
	 *   2) a utilizadores com a role "editor";
	 *   3) apenas em tempo de execução, via filtro 'user_has_cap' —
	 *      nunca persistida na role nem em qualquer opção da BD;
	 *   4) apenas dentro de pedidos verificados como sendo mesmo do
	 *      Customizer (preview, guardar via AJAX ou REST), com
	 *      verificação de nonce em cada um desses caminhos — não basta
	 *      a presença de um parâmetro de URL.
	 *
	 * Fora dessas condições, o Editor não tem manage_options em lado
	 * nenhum: continua sem acesso a Plugins, Utilizadores, ou qualquer
	 * outra página que dependa dessa capability. O plugin pode ficar
	 * ativo permanentemente em qualquer instalação WordPress — só tem
	 * efeito prático nos sites onde o Kadence está mesmo a ser usado.
	 */
	class Editor_Theme_Access_For_Kadence {

		/**
		 * Versão do plugin.
		 *
		 * @var string
		 */
		const VERSION = '2.1.0';

		/**
		 * Evita recursão no filtro user_has_cap (o próprio filtro, ao
		 * concluir que deve conceder a capability, não deve voltar a
		 * disparar-se a si próprio indiretamente).
		 *
		 * @var bool
		 */
		private static $processing = false;

		/**
		 * Regista os hooks do plugin.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'user_has_cap', array( __CLASS__, 'grant_runtime_caps' ), 10, 4 );
			add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		}

		/**
		 * Carrega o ficheiro de tradução do plugin.
		 *
		 * @return void
		 */
		public static function load_textdomain() {
			load_plugin_textdomain(
				'editor-theme-access-for-kadence',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages'
			);
		}

		/**
		 * Verifica se o tema ativo é o Kadence ou um tema-filho do
		 * Kadence. Usa get_template() (não get_stylesheet()) porque
		 * esse devolve sempre o tema-pai, mesmo quando um tema-filho
		 * está ativo — é essa verificação que o Kadence também faz
		 * internamente ao registar os seus painéis.
		 *
		 * Sem esta verificação, a elevação aplicar-se-ia a qualquer
		 * tema que uses no Customizer, não só ao Kadence.
		 *
		 * @return bool
		 */
		private static function is_kadence_theme_active() {
			/**
			 * Filtra o(s) slug(s) de tema considerados "Kadence" para
			 * efeitos deste plugin. Por predefinição, apenas o slug
			 * oficial 'kadence' (tema gratuito, disponível no
			 * WordPress.org). Útil se usares uma variante com slug
			 * diferente.
			 *
			 * @param string[] $slugs Lista de slugs de tema aceites.
			 */
			$kadence_slugs = apply_filters( 'etak_kadence_theme_slugs', array( 'kadence' ) );

			return in_array( get_template(), (array) $kadence_slugs, true );
		}

		/**
		 * Verifica se o utilizador é um Editor elegível (e não também
		 * Administrador, para quem isto já seria irrelevante).
		 *
		 * @param WP_User|null $user Utilizador a avaliar.
		 * @return bool
		 */
		private static function is_eligible_editor( $user ) {
			if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
				return false;
			}

			if ( ! in_array( 'editor', (array) $user->roles, true ) ) {
				return false;
			}

			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Deteta se o pedido HTTP atual é mesmo um pedido legítimo do
		 * Customizer ou do Kadence — com verificação de nonce nos
		 * caminhos que o permitem (AJAX, REST, guardar definições).
		 * A simples presença de um parâmetro de URL nunca é suficiente
		 * sozinha.
		 *
		 * @return bool
		 */
		private static function is_verified_kadence_context() {

			// 1. Pré-visualização nativa do Customizer.
			if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
				return true;
			}

			// 2. Carregamento do próprio ecrã do Customizer, com nonce.
			if ( is_admin() && isset( $_REQUEST['wp_customize'] ) && 'on' === $_REQUEST['wp_customize'] ) {
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				return ! empty( $nonce ) && wp_verify_nonce( $nonce, 'customize-preview' );
			}

			// 3. Pedidos AJAX do Customizer/Kadence, com nonce.
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				$allowed_actions = apply_filters(
					'etak_customizer_ajax_actions',
					array( 'customize_save', 'customize_refresh_nonces', 'customize_preview_settings' )
				);

				$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
				if ( ! in_array( $action, (array) $allowed_actions, true ) ) {
					return false;
				}

				$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
				return ! empty( $nonce ) && wp_verify_nonce( $nonce, 'customize_save' );
			}

			// 4. Pedidos REST ligados a definições/temas, com nonce.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

				$allowed_routes = apply_filters( 'etak_customizer_rest_routes', array( '/wp/v2/settings', '/wp/v2/themes' ) );

				$matches_route = false;
				foreach ( (array) $allowed_routes as $route_fragment ) {
					if ( false !== strpos( $route, $route_fragment ) ) {
						$matches_route = true;
						break;
					}
				}
				if ( ! $matches_route ) {
					return false;
				}

				$header_nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
				if ( ! empty( $header_nonce ) && wp_verify_nonce( $header_nonce, 'wp_rest' ) ) {
					return true;
				}

				$param_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				return ! empty( $param_nonce ) && wp_verify_nonce( $param_nonce, 'wp_rest' );
			}

			return false;
		}

		/**
		 * Filtra as capabilities do utilizador atual, concedendo
		 * manage_options (além de edit_theme_options e customize)
		 * apenas quando as duas condições se verificam: o utilizador é
		 * um Editor elegível, E o pedido atual é um pedido verificado
		 * do Customizer/Kadence.
		 *
		 * @param bool[]   $allcaps Capabilities já atribuídas ao utilizador.
		 * @param string[] $caps    Capabilities primitivas requeridas.
		 * @param array    $args    Argumentos adicionais.
		 * @param WP_User  $user    Utilizador em avaliação.
		 * @return bool[] Array de capabilities, possivelmente alterado.
		 */
		public static function grant_runtime_caps( $allcaps, $caps, $args, $user ) {

			if ( self::$processing ) {
				return $allcaps;
			}

			// Já é administrador (ou já tem manage_options por outra via) — nada a fazer.
			if ( ! empty( $allcaps['manage_options'] ) ) {
				return $allcaps;
			}

			// Só atua se o tema ativo for mesmo o Kadence (ou um tema-filho).
			if ( ! self::is_kadence_theme_active() ) {
				return $allcaps;
			}

			if ( ! self::is_eligible_editor( $user ) ) {
				return $allcaps;
			}

			/**
			 * Filtra se este Editor em concreto deve receber a elevação.
			 * Por predefinição aplica-se a todos os Editores elegíveis.
			 * Um mu-plugin ou functions.php pode restringir a IDs
			 * específicos sem alterar este plugin:
			 *
			 *   add_filter( 'etak_grant_theme_access', function ( $grant, $user ) {
			 *       return in_array( $user->ID, array( 5, 12 ), true );
			 *   }, 10, 2 );
			 *
			 * @param bool    $grant Se a elevação deve ser concedida a este utilizador.
			 * @param WP_User $user  Utilizador em avaliação.
			 */
			if ( ! apply_filters( 'etak_grant_theme_access', true, $user ) ) {
				return $allcaps;
			}

			if ( ! self::is_verified_kadence_context() ) {
				return $allcaps;
			}

			self::$processing = true;
			$allcaps['edit_theme_options'] = true;
			$allcaps['customize']          = true;
			$allcaps['manage_options']     = true;
			self::$processing = false;

			return $allcaps;
		}
	}

	Editor_Theme_Access_For_Kadence::init();
}
