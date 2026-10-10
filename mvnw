#!/bin/sh
MAVEN_PROJECTBASEDIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
WRAPPER_JAR="$MAVEN_PROJECTBASEDIR/.mvn/wrapper/maven-wrapper.jar"
if [ ! -f "$WRAPPER_JAR" ]; then
  echo "Maven Wrapper JAR is missing: $WRAPPER_JAR" >&2
  exit 1
fi
if [ -n "$JAVA_HOME" ]; then
  JAVA_EXE="$JAVA_HOME/bin/java"
else
  JAVA_EXE="java"
fi
exec "$JAVA_EXE" ${MAVEN_OPTS:-} ${MAVEN_DEBUG_OPTS:-} -classpath "$WRAPPER_JAR" "-Dmaven.multiModuleProjectDirectory=$MAVEN_PROJECTBASEDIR" org.apache.maven.wrapper.MavenWrapperMain "$@"
