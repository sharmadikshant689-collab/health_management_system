package com.curatech;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.config.annotation.CorsRegistry;
import org.springframework.web.servlet.config.annotation.ResourceHandlerRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

@Configuration
public class WebConfiguration implements WebMvcConfigurer {
    @Value("${curatech.project-dir:C:/xampp/htdocs/Curatech}")
    private String projectDirectory;

    @Override
    public void addCorsMappings(CorsRegistry registry) {
        registry.addMapping("/backend/**")
                .allowedOriginPatterns("*")
                .allowedMethods("GET", "POST", "OPTIONS")
                .allowedHeaders("*");
    }

    @Override
    public void addResourceHandlers(ResourceHandlerRegistry registry) {
        String frontendPath = projectDirectory.replace("\\", "/");
        if (!frontendPath.endsWith("/")) {
            frontendPath += "/";
        }
        registry.addResourceHandler("/frontened/**")
                .addResourceLocations("file:" + frontendPath + "frontened/");
    }
}
