SHELL			= /bin/bash

BUILD_DIR		= build
CONFIG_BASE_URL		= http:\/\/blueheronsresistance.com\/stats\/# escapes needed for sed
DEPLOY_DIR		= /sites/blueheronsresistance.com/stats/
GIT_REV			= $(shell git rev-parse HEAD)
GIT_TAG                 = $(shell git describe --exact-match $(GIT_REV))
INFO_FILE		= about
RSYNC_IGNORE_FILE	= .rsync_ignore

.SILENT:

build: clean css copy config

clean:
	echo "  Cleaning build dir...";
	rm -rf $(BUILD_DIR);

config: copy
	echo "  Updating configuration...";
	sed -i -e 's/\(\s*\)\(this.baseUrl = \).*/\1\2"$(CONFIG_BASE_URL)";/' $(BUILD_DIR)/scripts/StatTracker.js;
	sed -i -e 's/\(define("GOOGLE_REDIRECT_URL", "\).*\(authenticate?action=callback\)");/\1$(CONFIG_BASE_URL)\2");/' $(BUILD_DIR)/config.php
	sed -i -e 's/\(define("COMMIT_HASH", "\).*\");/\1${GIT_REV}\");/' $(BUILD_DIR)/config.php
	sed -i -e 's/\(define("TAG_NAME", "\).*\");/\1${GIT_TAG}\");/' $(BUILD_DIR)/config.php

copy: 
	echo "  Copying files...";
	rsync -r -a --exclude-from $(RSYNC_IGNORE_FILE) ./ $(BUILD_DIR);

css:
	echo "  Compiling LESS...";
	lessc style.less > style.css;

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
