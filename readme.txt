=== Editor Theme Access for Kadence ===
Contributors: seu-utilizador-wordpressorg
Tags: kadence, editor, capabilities, customizer, theme options
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dá à role Editor acesso completo ao Customizer do tema Kadence, sem escrever nada na base de dados. Plugin não oficial, sem afiliação com a Kadence WP.

== Description ==

O tema Kadence usa uma capability interna própria para decidir se
mostra os seus painéis de Customizer (Header, Footer, Colors & Fonts,
General, Posts/Pages Layout, Homepage Settings, etc.). Na prática,
essa capability só é satisfeita por `manage_options` — a capability
padrão `edit_theme_options`, que a role Editor normalmente não tem,
não é suficiente: um Editor com `edit_theme_options` continua a ver
apenas os painéis nativos do WordPress (Site Identity, Menus, Widgets,
CSS Adicional), mas não os painéis próprios do Kadence.

Este plugin resolve isso concedendo `manage_options` a utilizadores
Editor, mas de forma muito restrita:

1. **Só quando o tema ativo é mesmo o Kadence** (ou um tema-filho do
   Kadence). O plugin pode ficar instalado e ativo em qualquer site
   WordPress — não faz nada em sites que usem outro tema. Não é
   preciso desativar o plugin ao mudar de tema, nem instalar uma
   variante diferente para outros temas.
2. **Só em tempo de execução.** A capability nunca é gravada na role
   nem em qualquer opção da base de dados — é devolvida pelo filtro
   `user_has_cap` apenas durante o pedido HTTP em curso.
3. **Só dentro de pedidos verificados do Customizer**, com
   verificação de nonce em cada caminho possível: pré-visualização do
   Customizer, pedidos AJAX de gravação, e pedidos REST para
   `/wp/v2/settings` ou `/wp/v2/themes`. A simples presença de um
   parâmetro de URL nunca é suficiente sozinha — isto impede que um
   Editor obtenha `manage_options` noutras páginas do wp-admin apenas
   por manipular a querystring.

Fora dessas condições, o Editor não tem `manage_options` em lado
nenhum: continua sem acesso a Plugins, Utilizadores, ou qualquer outra
página que dependa dessa capability.

**Nada é escrito na base de dados**

* Não existe `add_cap()` sobre o objeto `WP_Role`, nem em qualquer
  hook de ativação, desativação ou `admin_init`.
* Não existe `register_setting`, `update_option` nem `update_user_meta`
  em nenhum ponto do plugin.
* Desativar o plugin repõe de imediato o comportamento nativo do
  WordPress para todos os Editores; desinstalar não deixa qualquer
  resíduo na BD.

**Extensibilidade**

Por predefinição, a elevação aplica-se a todos os Editores (dentro das
condições acima). Para restringir a utilizadores específicos, use o
filtro `etak_grant_theme_access` num mu-plugin ou no `functions.php`
do tema — sem alterar este plugin:

`add_filter( 'etak_grant_theme_access', function ( $grant, $user ) {`
`    return in_array( $user->ID, array( 5, 12 ), true );`
`}, 10, 2 );`

Também é possível ajustar as ações AJAX e rotas REST reconhecidas como
contexto do Customizer/Kadence, através dos filtros
`etak_customizer_ajax_actions` e `etak_customizer_rest_routes`, úteis
se o teu site tiver integrações adicionais com o Kadence que usem
ações ou rotas diferentes das reconhecidas por predefinição.

== Installation ==

1. Carregue a pasta `editor-theme-access-for-kadence` para o
   diretório `/wp-content/plugins/`, ou instale o plugin diretamente
   a partir do ecrã "Plugins" do WordPress.
2. Ative o plugin através do menu "Plugins" no WordPress.
3. Não é necessária qualquer configuração adicional. Os utilizadores
   com a role Editor passam a poder abrir e gravar todos os painéis do
   Customizer do Kadence.

== Frequently Asked Questions ==

= Preciso de desativar o plugin se mudar de tema? =

Não. O plugin verifica, a cada pedido, se o tema ativo (`get_template()`)
é `kadence`; se não for, não faz absolutamente nada. Podes deixá-lo
sempre ativo, mesmo em sites que não usem o Kadence, ou mudar de tema
sem te preocupares em desativá-lo primeiro.

= Uso uma variante do Kadence com outro slug de tema. Como ajusto isto? =

Use o filtro `etak_kadence_theme_slugs`:

`add_filter( 'etak_kadence_theme_slugs', function ( $slugs ) {`
`    return array_merge( $slugs, array( 'o-meu-slug-kadence' ) );`
`} );`

= Porque é que o plugin concede manage_options, e não só edit_theme_options? =

Porque o Kadence, para os seus próprios painéis (Header, Footer,
Colors & Fonts, General, Posts/Pages Layout, Homepage Settings),
verifica internamente uma capability que só `manage_options` satisfaz.
`edit_theme_options` sozinha mostra apenas os painéis nativos do
WordPress (Site Identity, Menus, Widgets, CSS Adicional).

= Isto não é perigoso — um Editor passa a ser quase Administrador? =

A elevação só existe durante pedidos verificados (nonce) do
Customizer/Kadence — nunca de forma persistente nem global. Um Editor
não ganha `manage_options` ao visitar Plugins, Utilizadores, ou
qualquer outra página do wp-admin; só dentro do próprio ecrã do
Customizer e das chamadas que ele gera. Ainda assim, é uma elevação
real: um Editor com acesso ao Customizer pode alterar qualquer
definição que viva lá (incluindo, por exemplo, "CSS Adicional", que
aceita CSS arbitrário, ou painéis de outros plugins que também
dependam de `manage_options` e apareçam no Customizer). Avalie este
compromisso antes de ativar o plugin num site com vários Editores.

= A permissão fica registada na base de dados? =

Não, em nenhuma circunstância. É sempre recalculada a cada pedido
através do filtro `user_has_cap`.

= Posso restringir isto a um Editor específico? =

Sim, através do filtro `etak_grant_theme_access` (ver secção
Description) num mu-plugin ou no `functions.php` do tema — sem
precisar de alterar este plugin.

= Este plugin é oficial da Kadence WP? =

Não. É um plugin de terceiros, independente, sem qualquer afiliação
com a Kadence WP.

== Screenshots ==

Sem capturas de ecrã — o plugin não tem interface gráfica.

== Changelog ==

= 2.1.0 =
* A elevação só atua quando o tema ativo é mesmo o Kadence (ou um
  tema-filho), verificado via get_template(). O plugin pode ficar
  sempre ativo, em qualquer site, sem efeito em temas que não sejam o
  Kadence.

= 2.0.0 =
* Mudança de arquitetura: a elevação passa a incluir manage_options
  (além de edit_theme_options e customize), já que o Kadence exige
  manage_options para mostrar os seus próprios painéis do Customizer.
  A concessão é estritamente limitada a pedidos verificados por nonce
  do Customizer/Kadence (preview, AJAX de gravação, REST), nunca
  persistida na base de dados.

= 1.6.0 =
* Renomeado de "Kadence Editor Theme Access" para "Editor Theme Access
  for Kadence".

= 1.5.0 =
* Removida a filtragem por ID de utilizador; edit_theme_options
  concedida a todos os Editores.

= 1.4.0 =
* Adicionadas variável de ambiente e ficheiro de texto como fontes de
  configuração da lista de Editores autorizados.

= 1.3.0 =
* Lista de Editores autorizados definida fora da base de dados.

= 1.2.0 =
* (Revertido) Página de definições em Utilizadores → Acesso Kadence.

= 1.1.0 =
* Simplificada a concessão da capability edit_theme_options.

= 1.0.0 =
* Primeira versão pública.

== Upgrade Notice ==

= 2.0.0 =
Mudança de comportamento: a partir desta versão, os Editores recebem
manage_options (não só edit_theme_options), mas apenas dentro de
pedidos verificados do Customizer/Kadence. Reveja a FAQ antes de
atualizar em produção.
