# GC2 Development

This guide shows how to set up the development environment for GC2.

We are using [VS Code's Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers) extension so all developers get the same development environment.

## Requirements

### Windows

- Docker Desktop 2.2+ - Download and install [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- WSL2 backend - Can be installed from windows store. [Docker Desktop setup](https://docs.docker.com/desktop/windows/wsl/)
- VS Code - Download and install [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

### MacOS

- Docker Desktop 2.0+
- VS Code - Download and install [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

### Linux

- Docker CE/EE 18.06+ and Docker Compose 1.21+. (The Ubuntu snap package is not supported)
- VS Code - Download and install [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

## Setup Repository for Local Development

In this step you will create a fork and clone it to your local machine. You only have to do the setup step once.

### Fork Repository

Sign in to your Github account and create a fork of Mapcentia's [Geocloud2 repository](https://github.com/mapcentia/geocloud2)

This is done by clicking the `Fork` button in the `top right corner`.

### Clone Your Fork in WSL

Go back to your own account and find the forked repository. Then click on `Clone or download` and copy the url.

Now on your local machine `open WSL` and clone the forked repository. This is done with the command:

```bash
git clone [paste the copied url from Github]

# Example
git clone https://github.com/JohnDoe/geocloud2.git
```

### Check That Fork is Origin

Your fork is called the `origin remote` and the project repository (Mapcentia's repository) is called the `upstream remote`.

```bash
# Show your current remotes
git remote -v

# If you don't see an origin remote, add it using:
git remote add origin [URL_OF_YOUR_FORK]

# Example
git remote add origin https://github.com/JohnDoe/geocloud2.git
```

### Add Mapcentia's Repository as Upstream

Go to Mapcentia's [Geocloud2 repository](https://github.com/mapcentia/geocloud2) and click `Clone or download` and copy the `HTTPS url`.

```bash
git remote add upstream [URL_OF_MAPCENTIA_PROJECT]

# Example
git remote add upstream https://github.com/mapcentia/geocloud2.git

# Check that you have an origin and upstream
git remote -v
```

## Local Development

### Pull Latest Changes From Upstream

Synchronize your local repository with the project repository:

```bash
git pull upstream master
```

### Create a Branch

When working on a feature or bug fix always create a new branch.

```bash
git checkout -b [BRANCH_NAME]

# Example
git checkout -b fixDocSpellingError
```

### Create a GC2 Image

To start VS Code from the commandline write `code .`

Go to the Dockerfile in the folder `docker/development` and do the following:

- Add the url of your fork. It's this step:

```yaml
RUN cd /var/www/ &&\
  git clone https://github.com/[ADD_GITHUB_USERNAME]/geocloud2.git --branch master
```

- Comment out fetch tag and checkout unless you have created the new branch remote. If you have then checkout the remote branch. It's this step:

```yaml
cd /var/www/geocloud2 &&\
  git fetch --tags &&\
  git checkout tags/2022.11.0
```

- Add extensions if you are going to need them.

Run the script `buildGc2Image.sh dev` This will build the Gc2 image. Remember to add the tag when calling the script.

```bash
sh buildGc2Image.sh dev
```

For more details see the description in the script.

In the docker-compose file make sure that the image that is used in the service gc2core matches the tag in the just created image.

### Start Dev Containers

Click F1 and write devcontainer choose `Rebuild and reopen in container` or `Reopen in Container`. If you have not build the dev container then you have to build it first. When it is build you can choose `Reopen in Container`.

This will start the dev container. When it's build a new VS Code window will open and show the code inside the dev container.

### Start GC2

In the new VS Code window open a terminal and `cd to docker/development` and write `docker-compose up`

This should start all the containers that is needed to run Gc2 Vidi.

### Access gc2core container

In the bottom left corner click on `Dev Container: Docker in Docker` and then choose `Attach to container` and choose `gc2core`.

A new VS Code window will open and you are now in the gc2core container.

### Setup the Application

Since we are sharing the volume with the localhost the geocloud2 folder in the gc2core image will be the same as the geocloud2 folder in the repository on the local machine. So changes inside the image will be reflected to the local machine. This makes sure that when we have made our changes we can push it to Github and the changes are also saved if the image is stopped.

Cd to `docker/development`.

Open the script `createBuildFilesForGc2.sh` and change the following:

- If you have the latest code and are in the right branch comment out:

```yaml
cd /var/www/geocloud2 &&\
  git fetch --tags &&\
  git checkout tags/2022.11.0
```

- Add the extensions that you need.

Run the script `sh createBuildFilesForGc2.sh`

You are now ready to develop.

## Push the Code

Go back to the repository on your local machine where you can see the changes that you have made. Be sure to check that there are no build files committed.

```bash
git add .
git commit -m "Fixed typo"

# Push the changes to your fork in Github
git push origin [BRANCH_NAME]

# Example
git push origin fixDocSpellingError
```

### Create Pull Request

Return to your fork on Github. Click the green `Compare & pull request` button to begin the pull request.

When you open a pull request you compare 2 branches where `Mapcentia is the master branch` and `your branch is the one you worked on like the branch fixDocSpellingError`.

Describe the changes you made and link to the issue.

When the changes has been accepted by Mapcentia go to your fork in your own Github account and sync the fork to get the latest code.
