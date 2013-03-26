#!/bin/bash
dd if=/dev/urandom of=upload_1 bs=1024 count=100
dd if=/dev/urandom of=upload_2 bs=1024 count=500
dd if=/dev/urandom of=upload_3 bs=1048576 count=1
dd if=/dev/urandom of=upload_4 bs=1048576 count=3
dd if=/dev/urandom of=upload_5 bs=1048576 count=6
dd if=/dev/urandom of=upload_6 bs=1048576 count=10
dd if=/dev/urandom of=upload_7 bs=1048576 count=20
dd if=/dev/urandom of=upload_8 bs=1048576 count=50
