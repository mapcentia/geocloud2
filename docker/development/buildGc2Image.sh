#!/bin/bash

# Description: This script builds an image of the GC2 backend.
# Add fork: Before running the script add your fork to the dockerfile in this folder.
# Args: When calling the function add a parameter that is used to tag the image.
# Run script: sh buildGc2Image.sh [Enter your tag]
# Example: sh buildGc2Image.sh dev

IMAGE_TAG=$1

if test -z "$IMAGE_TAG"
then
    echo "\$IMAGE_TAG is empty. Add a tag or read the description in the file."
    exit
fi

docker build -t gc2core:"$IMAGE_TAG" .
