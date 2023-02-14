# plesk-deployer
Automatic deployment to a shared hosting can be a real pain, sometimes even impossible. This Deployer recipe overwrites some of the common [Deployer](https://deployer.org/) tasks to allow deployment to Plesk chroot environments.

Deployment to shared hosting provided by [Metanet](https://www.metanet.ch/) has been successfully tested with these fixes. But they *may* also work on other providers who meet the requirements (see below).

I have also included a description of common problems with chroot environments below, which may help apply similar fixes to other deployment tools (Ansible, Capistrano, etc.).

## Who needs this
PHP developers who would like to deploy their applications to a chroot hosting environment using Deployer.

## Requirements
Your hosting should provide the following tools:

- SSH access
- Git
- PHP CLI
- Option to set the path to the web root
- Composer (optionally, if you need to install dependencies)

## Installation
Use Composer:

````
$ composer require florianmoser/plesk-deployer
````

## Applying the fixes
To apply the fixes, simply load the chroot_fixes.php file **after** the common Deployer recipe in your deploy.php file:

````php
<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/florianmoser/plesk-deployer/recipe/chroot_fixes.php';
````

You will also have to provide these Deployer environment variables:

- chroot_path_prefix: This prefix will be prepended to all SSH root paths (see next section on how to set this prefix)
- chroot_index_file: Path to web-project index file (usually index.php) relative to project root

````php
set( 'chroot_path_prefix', '<your-path-prefix>' );
set( 'chroot_index_file', 'index.php' );

````

Use your SSH path as deployment directory, when connecting to the host (see section below on how to get this path):

````php
host( 'your-host.com' )
    ->user( 'username' )
    ->set( 'deploy_path', '<your-ssh-root>' );
````

See `examples/deploy.php` for a full usage example.

## Setting up your Server
### 1. Create your domain in Plesk
Say you create a domain called "staging.domain.com", <your-ssh-root> will become "/staging.domain.com". In Plesk, if you create a new domain instead of a subdomain, <your-ssh-root> will most likely be "/httpdocs".

### 2. Create subdirectory
I recommend deploying in a subdirectory, as your web root will contain server specific files and directories like "cgi-bin". Say you create the directory "deploy", now <your-ssh-root> will become "/staging.domain.com/deploy". Use this as the deploy directory when connecting to your server.

### 3. Determine your absolute document root
Create the file `test.php` in your web root and add the following content:
````php
<?php
echo $_SERVER['DOCUMENT_ROOT'];
````
Open test.php in your web browser and copy the shown path. It should be something like "/home/httpd/vhosts/domain.com/staging.domain.com/deploy/current". From this path, subtract the <your-ssh-root> part. With the example from step 2. the path will become "/home/httpd/vhosts/domain.com". This is <your-path-prefix> which you should provide as Deployer environment 'chroot_path_prefix' (see above).

Don't forget to delete the test.php file.

### 4. Configure Plesk
You will need to tell the server to serve the deployed content. In Plesk, go to your newly created domain, select "Hosting setting" and go to the field called "Document root". There you will probably see "/staging.domain.com". Change this to your subdirectory created in step 2, and add "current", ie "/staging.domain.com/deploy/current".

"current" is the symlink, Deployer will create while deploying. Note that if the application you deploy has its index file in a subdirectory, you will need to add this subdirectory to your web root as well, ie "/staging.domain.com/deploy/current/web". Also note that Plesk already adds the leading slash "/".

## Deploy
Deploy as you are used to with

````
$ dep deploy
````

If you are having trouble in the deployment process while checking out the Git repository, read the section "No SSH agent forwarding" below.

# Common problems while deploying to chroot environment
If you simply use the Deployer fixes, you don't have to read the following sections. Read them, if you would like to know more about the details of chroot deployment.

## Different directory structure
One of the problems with a chroot host is, that your SSH root differs from your server root. The directory structure while using SSH (during deployment) is different than the directory structure Apache uses to serve your site. Your SSH root is a subdirectory of the real root. The provided tasks fix this issue, but they need your full server path prefix to work.

These tasks solve this problem by deploying to the SSH directory, but change the symlink to the absolute path after deployment. To preserve atomic deployments, this has to happen shortly **before** the symlink is updated. This can easily be achieved in Deployer, as Deployer creates a "release" symlink during deployment, which is then used to update the "current" symlink. So updating the "release" symlink before the "symlink" hook solves that problem.

Of course symlinks also have to be updated for all shared directories and for rollback tasks.

## Limited commands
Most SSH access to chroot environments has only a limited set of bash commands available. Many commands used by Deployer are not available on a limited SSH access. The solution is to use PHP CLI to run alternative functions in PHP.

## No SSH agent forwarding
[SSH agent forwarding](https://developer.github.com/v3/guides/using-ssh-agent-forwarding/) is disabled on most shared hosting accounts. This means you can't have a local private SSH key to your Git repository to clone the repo to the server. In this case you have to generate a public/private SSH key pair on the server itself.

To do so, run these commands on your server (create the .ssh directory only, if not available):

````
$ mkdir ~/.ssh
$ chmod 700 ~/.ssh
$ cd .ssh
$ ssh-keygen -t rsa
````

Do not choose a passphrase (simply press enter), otherwise you won't be able to automatically deploy.

Then you will need to add the server's public key to your repository. Run:

````
$ cat ~/.ssh/id_rsa.pub
````

This will display your public key, which you can copy/paste to your repository provider.

**NOTE:** do not allow write permission or read permission to other repositories, otherwise you may loose your code if your server gets compromised.

In [Bitbucket](https://bitbucket.org/) for example, you can open your repo, go to "Settings", "Access Keys" and klick on "Add key" to paste your public key, to grant read-only permission to your server. 

## No FPM restart
After deployment to a server where PHP is running with FPM, FPM service should be restarted. This is not possible on a shared hosting.

The fix for this problem is still experimental. But it seems, that touching the index file from the previous release (this is the one currently in the FPM cache), forces FPM to reload its cache (and thereby cache the new release, as the current symlink has already been updated at that time).

## Unsupported GIT_SSH_COMMAND
Deployer 7 added a default value of `ssh -o StrictHostKeyChecking=accept-new` to the environment variable *GIT_SSH_COMMAND* as described in the [docs](https://deployer.org/docs/7.x/recipe/deploy/update_code#git_ssh_command). Some hosting companies don't support this option.

In this case, simply revert the value back to `ssh`:

````php
    set('git_ssh_command', 'ssh');
````

Thanks to [deflox](https://github.com/deflox) for mentioning this [issue](https://github.com/FlorianMoser/plesk-deployer/issues/3).
