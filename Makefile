SHELL			= /bin/bash
.SILENT:

BUILD_DIR		= build
RSYNC_IGNORE_FILE	= .rsync_ignore

build:
	echo "  Copying files...";
	rsync -r --exclude-from $(RSYNC_IGNORE_FILE) ./ $(BUILD_DIR);
	echo "  Compiling LESS...";
	lessc style.less > $(BUILD_DIR)/style.css;

clean:
	echo "  Cleaning build dir...";
	rm -rf $(BUILD_DIR);
