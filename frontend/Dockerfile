# Image de l'interface KaarPro : React compilé, servi par Nginx qui transmet /api à PHP-FPM

# Étape 1 : compilation
FROM node:22-alpine AS compilation
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# L'API est servie sur le même domaine, sous /api
ENV VITE_API_URL=/api
RUN npm run build

# Étape 2 : serveur web
FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=compilation /app/dist /usr/share/nginx/html
EXPOSE 80
