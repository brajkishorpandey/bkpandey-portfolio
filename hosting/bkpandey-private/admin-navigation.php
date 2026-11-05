<?php
declare(strict_types=1);
function bp_admin_section(array $query): string {
    foreach(['account','database','site','contact','traffic','new'] as $section)if(isset($query[$section]))return $section;
    return 'articles';
}
function bp_admin_navigation(array $query): string {
    $current=bp_admin_section($query);$html='<nav class="admin-toolbar admin-nav" aria-label="Publishing navigation">';
    foreach(['new'=>['New article +','/admin?new=1'],'articles'=>['All articles','/admin'],'site'=>['Website & résumé','/admin?site=1'],'contact'=>['Contact email','/admin?contact=1'],'traffic'=>['Traffic reports','/admin?traffic=1'],'account'=>['Account & password','/admin?account=1'],'database'=>['Database','/admin?database=1']] as $key=>[$label,$href]){
        $active=$current===$key;
        $html.='<a class="btn'.($active?' btn-primary is-active':'').'" href="'.bp_h($href).'"'.($active?' aria-current="page"':'').'>'.bp_h($label).'</a>';
    }
    return $html.'<a class="btn" href="/blog/" target="_blank" rel="noopener">View blog ↗</a><form method="post"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'"><button class="btn" name="action" value="logout">Sign out</button></form></nav>';
}
