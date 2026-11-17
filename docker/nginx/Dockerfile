FROM node:22-alpine AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

FROM nginx:1.27-alpine
WORKDIR /var/www/html
COPY public ./public
COPY --from=frontend /var/www/html/public/build ./public/build
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
