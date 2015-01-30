SHELL             = /bin/bash

BUILD_DIR         = build
CONFIG_BASE_URL   = https:\/\/blueheronsresistance.com\/stats\/# escapes needed for sed
DEPLOY_DIR        = /sites/blueheronsresistance.com/stats/
GIT_VER           = $(shell git describe)
GIT_REV           = $(shell git rev-parse HEAD)
INFO_FILE         = about
RSYNC_IGNORE_FILE = .rsync_ignore

.SILENT:

build: clean copy

clean:
	echo "  Cleaning build dir...";
	rm -rf $(BUILD_DIR);

config: pre-copy-config

copy: config
	echo "  Copying files...";
	rsync -r -a --exclude-from $(RSYNC_IGNORE_FILE) ./ $(BUILD_DIR);
	$(MAKE) post-copy-config

deploy:
	echo "  Deploying...";
	rm -rf $(DEPLOY_DIR)*;
	cp -r $(BUILD_DIR)/* $(DEPLOY_DIR);
	# Change the user:group below to match your server setup
	chown -R root:www-data $(DEPLOY_DIR);
	chmod -R 0750 $(DEPLOY_DIR);
	rm -rf $(DEPLOY_DIR)/uploads;
	mkdir $(DEPLOY_DIR)/uploads;
	chmod 0777 $(DEPLOY_DIR)/uploads;

.PHONY:

pre-copy-config:
	echo "  Updating configuration...";
	sed -i -e 's/\(define("COMMIT_HASH",\s*"\).*\");/\1${GIT_REV}\");/' config.php
	sed -i -e 's/\(define("VERSION",\s*"\).*\");/\1${GIT_VER}\");/' config.php

post-copy-config:
	echo "  Updating configuration...";
	sed -i -e 's/\(define("GOOGLE_REDIRECT_URL",\s*"\).*\(authenticate?action=callback\)");/\1$(CONFIG_BASE_URL)\2");/' $(BUILD_DIR)/config.php
