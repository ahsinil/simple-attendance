# --- Build Stage ---
FROM node:lts-alpine as build-stage

# Set working directory
WORKDIR /app
# Copy package files first to leverage Docker caching
COPY package*.json ./
# Install dependencies
RUN npm install
# Copy the rest of the project files
COPY . .

# Accept the argument from docker-compose
ARG VITE_API_URL
# Make it available to the build process
ENV VITE_API_URL=$VITE_API_URL

# Build the app for production (creates the /dist folder)
RUN npm run build

# --- Stage 2: Production Stage ---
FROM nginx:stable-alpine as production-stage

# Copy the build output from the previous stage to the Nginx html folder
COPY --from=build-stage /app/dist /usr/share/nginx/html
# Copy our custom Nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Expose port 80
EXPOSE 80

# Start Nginx in the foreground
CMD ["nginx", "-g", "daemon off;"]