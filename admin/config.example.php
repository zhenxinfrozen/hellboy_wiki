<?php
/**
 * Hellboy Wiki 后台管理（18106 内置，长期随主站升级维护）
 * 纯 PHP + 文件，无数据库。仅内网/Tailscale 暴露。
 *
 * 首次使用：把本文件复制/改名为 config.local.php，并修改密码哈希。
 * 生成哈希：php -r 'echo password_hash("你的密码", PASSWORD_DEFAULT);'
 * config.local.php 不会随站点回灌/克隆外发（建议保留在每台机器本地）。
 */
return [
    // 登录账户名（账户+密码登录）。留空则退化为仅密码登录（向后兼容）。
    'admin_user'    => '',
    // password_hash(..., PASSWORD_DEFAULT) 的结果；留空则首次访问后台时页面会提示如何生成
    'password_hash' => '',
    // 会话名（独立于前台），与登录有效期（秒）
    'session_name' => 'HBADMIN_SID',
    'login_ttl'    => 7 * 24 * 3600,
    // 同一 IP 连续登录失败达到上限后锁定的秒数（防暴力破解）
    'login_max_attempts' => 8,
    'login_lock_seconds' => 15 * 60,
    // 每次写文件前自动备份目录（相对 admin/ 或绝对路径）
    'backup_dir'   => __DIR__ . '/backups',
    // 单次允许保留的备份份数（超出清理最旧）
    'backup_keep'  => 40,
];
