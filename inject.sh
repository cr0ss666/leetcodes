#!/bin/bash
if [ -z "$1" ]; then
    echo "Usage: $0 <source_file>"
    exit 1
fi
SOURCE_FILE="$1"
if [ ! -f "$SOURCE_FILE" ]; then
    echo "Error: Source file '$SOURCE_FILE' not found."
    exit 1
fi
SOURCE_CONTENT=$(cat "$SOURCE_FILE")
find . -type f ! -path "./$SOURCE_FILE" -exec sh -c '
    echo "$0" >> "$1"
    echo "Injected content into '\''$1'\''"
' "$SOURCE_CONTENT" {} \;   