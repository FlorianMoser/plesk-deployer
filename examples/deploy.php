<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/florianmoser/plesk-deployer/recipe/chroot_fixes.php';

// Configuration

set( 'repository', '' );
set( 'chroot_path_prefix', '/home/httpd/vhosts/domain.com' );
set( 'chroot_index_file', 'web/index.php' );

// Hosts
host( 'your-host.com' )
	->stage( 'staging' )
	->set( 'deploy_path', '/staging.domain.com/deploy' );

// Tasks
desc( 'Deploy your project' );
task( 'deploy', [
	'deploy:prepare',
	'deploy:lock',
	'deploy:release',
	'deploy:update_code',
	'deploy:shared',
	'deploy:writable',
	'deploy:vendors',
	'deploy:clear_paths',
	'deploy:symlink',
	'deploy:unlock',
	'cleanup',
	'success'
] );

// [Optional] if deploy fails automatically unlock.
after( 'deploy:failed', 'deploy:unlock' );